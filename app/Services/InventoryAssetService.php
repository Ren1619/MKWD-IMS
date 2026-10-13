<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\User;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableValueClass;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryAssetService
{
    public function __construct(
        private PropertyAccountabilityService $accountability,
        private InventoryAssetLifecycleService $lifecycle,
    ) {}

    /** @param array<string, mixed> $data */
    public function updateDetails(InventoryAsset $asset, array $data, User $actor): InventoryAsset
    {
        abort_unless($actor->can('manage-inventory'), 403);

        return DB::transaction(function () use ($asset, $data): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAsset->lifecycle_status === AssetLifecycleStatus::TransferredOut) {
                throw new InvalidArgumentException('Permanently transferred property cannot be edited.');
            }
            if ($lockedAsset->current_custodian_reference_id !== null && array_key_exists('location', $data) && $data['location'] !== $lockedAsset->location) {
                throw new InvalidArgumentException('Use an approved PTR to change the location of assigned property.');
            }
            $lockedAsset->update($data);

            return $lockedAsset->refresh();
        });
    }

    /** @param array<string, mixed>|null $transferEvidence */
    public function assign(
        InventoryAsset $asset,
        HrisReference $reference,
        User $actor,
        ?string $replacementJustification = null,
        ?array $transferEvidence = null,
        bool $initialAssignmentOnly = false,
    ): InventoryAsset {
        return DB::transaction(function () use ($asset, $reference, $actor, $replacementJustification, $transferEvidence, $initialAssignmentOnly): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($initialAssignmentOnly && $lockedAsset->current_custodian_reference_id !== null) {
                throw new InvalidArgumentException('Use an approved Property Transfer Report for assigned property.');
            }

            if (! $lockedAsset->is_assignable) {
                throw new InvalidArgumentException('Disposed or lost assets cannot be assigned.');
            }

            if ($lockedAsset->accounting_classification === AssetAccountingClassification::SemiExpendable
                && $lockedAsset->semi_expendable_value_class === SemiExpendableValueClass::Low
                && blank($replacementJustification)
                && InventoryAsset::query()
                    ->whereKeyNot($lockedAsset->getKey())
                    ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
                    ->where('semi_expendable_value_class', SemiExpendableValueClass::Low)
                    ->where('category_id', $lockedAsset->category_id)
                    ->where('subcategory_id', $lockedAsset->subcategory_id)
                    ->where('current_custodian_reference_id', $reference->id)
                    ->whereIn('accountability_status', [
                        SemiExpendableAccountabilityStatus::PendingRecipient,
                        SemiExpendableAccountabilityStatus::Accountable,
                    ])
                    ->exists()) {
                throw new InvalidArgumentException('Employee already has an active low-valued accountability of the same property type. Return it or provide a replacement justification.');
            }

            $supersededDocument = $lockedAsset->accountabilityDocuments()
                ->whereIn('status', ['pending_recipient', 'active'])
                ->first();
            $outgoingCustodian = $lockedAsset->currentCustodian()->first();

            $this->accountability->closeCurrentForAsset($lockedAsset, $actor, 'superseded', 'Custody transferred to a different accountable person.');
            $lockedAsset->custodianAssignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            $assignment = $lockedAsset->custodianAssignments()->create([
                'hris_reference_id' => $reference->id,
                'assigned_at' => now(),
            ]);

            $lockedAsset->update(['current_custodian_reference_id' => $reference->id]);
            $issuedDocument = $this->accountability->issue($lockedAsset->refresh(), $assignment, $actor, $supersededDocument);

            if ($supersededDocument !== null) {
                $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::Transfer, [
                    'event_date' => today()->toDateString(),
                    'reference_no' => $transferEvidence['transfer_no'] ?? sprintf('ITR-%s-%06d', today()->format('Y'), $issuedDocument->getKey()),
                    'from_location' => $transferEvidence['from_location'] ?? $lockedAsset->location,
                    'to_location' => $lockedAsset->location,
                    'notes' => "Accountability transferred from {$outgoingCustodian?->name} to {$reference->name}.",
                    'details' => [
                        'from_custodian_reference_id' => $outgoingCustodian?->getKey(),
                        'to_custodian_reference_id' => $reference->getKey(),
                        'superseded_document_id' => $supersededDocument->getKey(),
                        'replacement_document_id' => $issuedDocument->getKey(),
                        'transfer_evidence' => $transferEvidence,
                    ],
                ], $actor);
            }
            $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::Assignment, [
                'event_date' => today()->toDateString(),
                'reference_no' => $reference->code,
                'notes' => "Assigned to {$reference->name}.",
                'details' => [
                    'hris_reference_id' => $reference->getKey(),
                    'document_id' => $issuedDocument->getKey(),
                    'replacement_justification' => $replacementJustification,
                ],
            ], $actor);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    public function unassign(InventoryAsset $asset, User $actor, string $reason = 'Property returned to the Property Unit.'): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $actor, $reason): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedAsset->lifecycle_status === AssetLifecycleStatus::TransferredOut) {
                throw new InvalidArgumentException('Property transferred to another agency cannot be returned through internal custody controls.');
            }
            $currentDocument = $lockedAsset->accountabilityDocuments()
                ->whereIn('status', ['pending_recipient', 'active'])
                ->first();
            $this->accountability->closeCurrentForAsset($lockedAsset, $actor, 'returned', $reason);
            $lockedAsset->custodianAssignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
            $lockedAsset->update([
                'current_custodian_reference_id' => null,
            ]);
            if ($lockedAsset->accounting_classification === AssetAccountingClassification::SemiExpendable) {
                $lockedAsset->update([
                    'physical_status' => SemiExpendablePhysicalStatus::Returned,
                    'accountability_status' => SemiExpendableAccountabilityStatus::Returned,
                    'accountability_expires_at' => null,
                    'available_for_reissue' => $lockedAsset->condition_status->allowsBorrowing(),
                ]);
            }
            $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::Return, [
                'event_date' => today()->toDateString(),
                'reference_no' => sprintf('RRSP-%s-%06d', today()->format('Y'), $currentDocument?->getKey() ?? $lockedAsset->getKey()),
                'notes' => $reason,
                'details' => ['document_id' => $currentDocument?->getKey()],
            ], $actor);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    /** @param array<string, mixed> $data */
    public function borrow(InventoryAsset $asset, array $data, User $actor): InventoryAssetBorrowing
    {
        return DB::transaction(function () use ($asset, $data, $actor): InventoryAssetBorrowing {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->activeBorrowing()->exists()) {
                throw new InvalidArgumentException('This asset is already borrowed.');
            }

            if (! $lockedAsset->is_borrowable) {
                throw new InvalidArgumentException('Only active assets in good or fair condition can be borrowed.');
            }

            $borrowing = $lockedAsset->borrowings()->create([
                ...$data,
                'borrowed_at' => now(),
                'status' => 'borrowed',
            ]);
            $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::Borrowed, [
                'event_date' => today()->toDateString(),
                'reference_no' => $data['borrower_name'] ?? null,
                'notes' => $data['notes'] ?? 'Property released for temporary borrowing.',
                'details' => [
                    'borrowing_id' => $borrowing->getKey(),
                    'borrower_reference_id' => $data['borrower_reference_id'] ?? null,
                ],
            ], $actor);

            return $borrowing;
        });
    }

    public function returnBorrowed(InventoryAsset $asset, User $actor, ?string $notes = null): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $actor, $notes): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $borrowing = $lockedAsset->activeBorrowing()->first();

            if (! $borrowing) {
                throw new InvalidArgumentException('This asset has no active borrowing record.');
            }

            $borrowing->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_notes' => $notes,
            ]);
            $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::BorrowingReturn, [
                'event_date' => today()->toDateString(),
                'notes' => $notes ?? 'Borrowed property returned.',
                'details' => ['borrowing_id' => $borrowing->getKey()],
            ], $actor);

            $lockedAsset->refresh();
            $lockedAsset->load(['category', 'currentCustodian', 'activeBorrowing.borrowerReference']);

            return $lockedAsset;
        });
    }

    /** @param array{lifecycle_status: string, condition_status: string} $state */
    public function updateState(InventoryAsset $asset, array $state, User $actor): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $state, $actor): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $nextLifecycle = AssetLifecycleStatus::from($state['lifecycle_status']);
            if ($lockedAsset->lifecycle_status === AssetLifecycleStatus::Disposed) {
                throw new InvalidArgumentException('Completed disposal cannot be reversed through operational state editing.');
            }
            if ($lockedAsset->lifecycle_status === AssetLifecycleStatus::TransferredOut || $nextLifecycle === AssetLifecycleStatus::TransferredOut) {
                throw new InvalidArgumentException('Permanent agency transfers require the PTR workflow and cannot be reversed by a state edit.');
            }

            if (in_array($nextLifecycle, [AssetLifecycleStatus::Disposed, AssetLifecycleStatus::Lost], true)) {
                throw new InvalidArgumentException('Record loss or disposal as a documented PPE lifecycle transaction.');
            }

            $oldState = [
                'lifecycle_status' => $lockedAsset->lifecycle_status->value,
                'condition_status' => $lockedAsset->condition_status->value,
            ];
            $lockedAsset->update($state);
            $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::StateChanged, [
                'event_date' => today()->toDateString(),
                'notes' => 'Operational lifecycle or condition state updated.',
                'details' => ['from' => $oldState, 'to' => $state],
            ], $actor);

            return $lockedAsset->refresh();
        });
    }
}
