<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryPropertyTransfer;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryPropertyTransferService
{
    public function __construct(
        private InventoryAssetService $custody,
        private InventoryAssetLifecycleService $lifecycle,
        private PropertyAccountabilityService $accountability,
        private PpeDepreciationCalculator $depreciation,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): InventoryPropertyTransfer
    {
        abort_unless($actor->can('manage-inventory'), 403);

        return DB::transaction(function () use ($data, $actor): InventoryPropertyTransfer {
            $asset = InventoryAsset::query()->whereKey($data['inventory_asset_id'])->lockForUpdate()->firstOrFail();
            $source = $asset->accountabilityDocuments()->whereIn('status', ['active', 'pending_recipient'])->first();
            if ($source === null || $asset->current_custodian_reference_id === null || $source->recipient_reference_id !== $asset->current_custodian_reference_id || ! $asset->is_assignable) {
                $this->fail('An assignable property with an existing PAR/ICS is required.');
            }
            if (InventoryPropertyTransfer::query()->where('inventory_asset_id', $asset->getKey())->whereIn('status', ['submitted', 'verified', 'approved'])->exists()) {
                $this->fail('This property already has an open transfer request.');
            }
            $external = in_array($data['transfer_type'], ['agency_transfer', 'donation'], true);
            $recipient = $external ? null : HrisReference::query()->whereKey($data['recipient_reference_id'])->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->firstOrFail();
            $relocation = $data['transfer_type'] === 'relocation';
            if (! $external && $relocation !== ($recipient->getKey() === $asset->current_custodian_reference_id)) {
                $this->fail('Relocation retains the same officer; other internal transfers require a different officer.');
            }
            if ($relocation && $data['destination_location'] === $asset->location) {
                $this->fail('Relocation requires a different destination location.');
            }
            $transfer = InventoryPropertyTransfer::query()->create([
                'inventory_asset_id' => $asset->getKey(), 'source_document_id' => $source->getKey(),
                'recipient_reference_id' => $recipient?->getKey(), 'transfer_type' => $data['transfer_type'],
                'reason' => $data['reason'], 'destination_location' => $data['destination_location'],
                'desired_date' => $data['desired_date'], 'source_snapshot' => $this->sourceSnapshot($asset, $source),
                'recipient_snapshot' => $external ? ['name' => $data['agency_officer_name'], 'office' => $data['agency_name'], 'position' => $data['agency_officer_position'], 'kind' => 'external_agency'] : $this->recipientSnapshot($recipient),
                'replacement_justification' => $data['replacement_justification'] ?? null,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $transfer->update(['document_no' => sprintf('PTR-%s-%06d', today()->format('Y'), $transfer->getKey())]);
            $this->record($transfer, $actor, 'submit', null, 'submitted', 'Transfer request prepared on behalf of the accountable officer.', []);

            return $transfer;
        });
    }

    /** @return list<string> */
    public function allowedActions(InventoryPropertyTransfer $transfer, User $actor): array
    {
        if (! $actor->can('manage-inventory')) {
            return [];
        }
        $actions = match ($transfer->status) {
            'submitted' => $actor->getKey() !== $transfer->created_by_user_id ? ['verify', 'reject', 'cancel'] : ['cancel'],
            'verified' => ! in_array($actor->getKey(), [$transfer->created_by_user_id, $transfer->verified_by_user_id], true) ? ['approve', 'reject', 'cancel'] : ['cancel'],
            'approved' => ['complete', 'cancel'],
            default => [],
        };

        return $actions;
    }

    /** @param array<string, mixed> $data */
    public function transition(InventoryPropertyTransfer $transfer, array $data, User $actor): InventoryPropertyTransfer
    {
        abort_unless($actor->can('manage-inventory'), 403);

        return DB::transaction(function () use ($transfer, $data, $actor): InventoryPropertyTransfer {
            $asset = InventoryAsset::withTrashed()->whereKey($transfer->inventory_asset_id)->lockForUpdate()->firstOrFail();
            $locked = InventoryPropertyTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            if (! in_array($action, $this->allowedActions($locked, $actor), true)) {
                $this->fail('This decision is not permitted for the current stage or actor.');
            }
            $from = $locked->status;
            if (in_array($action, ['reject', 'cancel'], true)) {
                $to = $action === 'reject' ? 'rejected' : 'cancelled';
            } else {
                $source = $asset->accountabilityDocuments()->whereKey($locked->source_document_id)->whereIn('status', ['active', 'pending_recipient'])->first();
                $recipient = $locked->isExternal() ? null : HrisReference::query()->whereKey($locked->recipient_reference_id)->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->first();
                if ($asset->trashed() || ! $asset->is_assignable || $source === null || (! $locked->isExternal() && $recipient === null)
                    || $this->sourceSnapshot($asset, $source) !== $locked->source_snapshot
                    || (! $locked->isExternal() && $this->recipientSnapshot($recipient) !== $locked->recipient_snapshot)) {
                    $this->fail('Property, custody, or recipient details changed. Cancel and prepare a corrected transfer.');
                }
                $to = match ($action) {
                    'verify' => 'verified', 'approve' => 'approved', 'complete' => 'completed', default => $this->fail('Unknown transfer decision.')
                };
                if ($action === 'verify') {
                    $locked->update(['verified_by_user_id' => $actor->getKey()]);
                } elseif ($action === 'approve') {
                    if ($locked->isExternal() && (blank($data['authority_reference'] ?? null) || blank($data['accounting_clearance_reference'] ?? null))) {
                        $this->fail('Permanent agency transfers require transfer authority and Accounting Unit clearance.');
                    }
                    $locked->update(['approved_by_user_id' => $actor->getKey()]);
                } else {
                    $approval = $locked->actions()->where('action', 'approve')->sole();
                    if ($data['handover_date'] < $approval->created_at->toDateString() || $data['handover_date'] > today()->toDateString()) {
                        $this->fail('Physical handover must be on or after approval and cannot be future dated.');
                    }
                    $evidence = [
                        'transfer_id' => $locked->getKey(), 'transfer_no' => $locked->document_no,
                        'transfer_type' => $locked->transfer_type, 'reason' => $locked->reason,
                        'approved_by_name' => $approval->actor_name,
                        'approval_date' => $approval->created_at->toDateString(),
                        'handover_date' => $data['handover_date'], 'inspection_reference' => $data['inspection_reference'],
                        'release_reference' => $data['release_reference'], 'receipt_reference' => $data['receipt_reference'],
                        'handover_attestation' => $data['attestation'],
                        'from_location' => $asset->location,
                    ];
                    if ($locked->isExternal()) {
                        $this->completeExternal($locked, $asset, $source, $evidence, $data, $actor);
                    } elseif ($locked->transfer_type === 'relocation') {
                        $asset->update(['location' => $locked->destination_location, 'condition_status' => AssetConditionStatus::from($data['condition_status'])]);
                        $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Transfer, [
                            'event_date' => today()->toDateString(), 'reference_no' => $locked->document_no,
                            'from_location' => $evidence['from_location'], 'to_location' => $locked->destination_location,
                            'condition_status' => $data['condition_status'], 'notes' => $locked->reason,
                            'details' => ['transfer_evidence' => $evidence],
                        ], $actor);
                        $locked->update(['replacement_document_id' => $source->getKey()]);
                    } else {
                        $asset->update(['location' => $locked->destination_location, 'condition_status' => AssetConditionStatus::from($data['condition_status'])]);
                        try {
                            $this->custody->assign($asset, $recipient, $actor, $locked->replacement_justification, $evidence);
                        } catch (\InvalidArgumentException $exception) {
                            $this->fail($exception->getMessage());
                        }
                        $replacement = $asset->accountabilityDocuments()->where('supersedes_document_id', $source->getKey())->sole();
                        $locked->update(['replacement_document_id' => $replacement->getKey()]);
                    }
                    $locked->update(['completed_at' => now()]);
                }
            }
            $locked->update(['status' => $to]);
            $this->record($locked, $actor, $action, $from, $to, $data['attestation'], $data);

            return $locked->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(InventoryAsset $asset, PropertyAccountabilityDocument $document): array
    {
        return [
            ...array_intersect_key($asset->getAttributes(), array_flip(['current_custodian_reference_id', 'name', 'description', 'property_number', 'serial_number', 'acquisition_date', 'acquisition_cost', 'quantity_per_property_card', 'unit_of_measure', 'fund_cluster', 'location', 'condition_status', 'lifecycle_status', 'accounting_classification'])),
            'document_no' => $document->document_no, 'recipient_name' => $document->recipient_name,
            'recipient_code' => $document->recipient_code, 'recipient_position' => $document->recipient_position,
            'recipient_office' => $document->recipient_office,
        ];
    }

    /** @return array<string, mixed> */
    private function recipientSnapshot(HrisReference $reference): array
    {
        return ['name' => $reference->name, 'code' => $reference->code, 'office' => $reference->officeName(), 'position' => data_get($reference->metadata, 'position.name')];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $data
     */
    private function completeExternal(InventoryPropertyTransfer $transfer, InventoryAsset $asset, PropertyAccountabilityDocument $source, array $evidence, array $data, User $actor): void
    {
        if (blank($data['accounting_reference'] ?? null) || ! in_array($data['ownership_transfer_confirmed'] ?? null, [true, 1, '1', 'true', 'yes', 'on'], true)) {
            $this->fail('Record Accounting Unit posting evidence and confirmation of permanent ownership transfer.');
        }
        if ($asset->activeBorrowing()->exists()) {
            $this->fail('Resolve the legacy borrowing before permanently transferring this property.');
        }
        $approval = $transfer->actions()->where('action', 'approve')->sole();
        $ppe = $asset->accounting_classification === AssetAccountingClassification::Ppe;
        $valuation = $ppe ? $this->depreciation->calculate($asset, today()->subDay()) : null;
        $evidence = [...$evidence,
            'external_ownership_transfer' => true, 'recipient' => $transfer->recipient_snapshot,
            'authority_reference' => $approval->evidence['authority_reference'],
            'accounting_clearance_reference' => $approval->evidence['accounting_clearance_reference'],
            'accounting_reference' => $data['accounting_reference'],
            'valuation_as_of' => today()->subDay()->toDateString(),
            'gross_carrying_amount' => $valuation?->grossCarryingAmount,
            'accumulated_depreciation' => $valuation?->accumulatedDepreciation,
            'impairment_losses' => $valuation?->impairmentLosses,
            'carrying_amount' => $valuation?->carryingAmount,
        ];
        $this->accountability->closeCurrentForAsset($asset, $actor, 'transferred_out', 'Permanent agency transfer under '.$transfer->document_no);
        $asset->custodianAssignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
        $asset->update(['current_custodian_reference_id' => null, 'location' => $transfer->destination_location,
            'condition_status' => AssetConditionStatus::from($data['condition_status']), 'lifecycle_status' => AssetLifecycleStatus::TransferredOut,
            ...($ppe ? ['accounting_status' => AssetAccountingStatus::Derecognized, 'derecognized_at' => today()] : [
                'physical_status' => SemiExpendablePhysicalStatus::Transferred,
                'accountability_status' => SemiExpendableAccountabilityStatus::TransferredOut,
                'available_for_reissue' => false, 'accountability_expires_at' => null,
            ]),
        ]);
        $event = $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Transfer, [
            'event_date' => today()->toDateString(), 'reference_no' => $transfer->document_no,
            'from_location' => $evidence['from_location'], 'to_location' => $transfer->destination_location,
            'condition_status' => $data['condition_status'], 'quantity' => $asset->quantity_per_property_card,
            'notes' => $transfer->reason, 'details' => ['superseded_document_id' => $source->getKey(), 'document_id' => $source->getKey(), 'transfer_evidence' => $evidence],
        ], $actor);
        if ($ppe) {
            $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::Derecognition, [
                'event_date' => today()->toDateString(), 'reference_no' => $data['accounting_reference'],
                'notes' => 'Permanent ownership transferred to another agency; Accounting Unit posting reference recorded.',
                'details' => ['transfer_event_id' => $event->getKey(), 'transfer_evidence' => $evidence],
            ], $actor);
        }
    }

    /** @param array<string, mixed> $evidence */
    private function record(InventoryPropertyTransfer $transfer, User $actor, string $action, ?string $from, string $to, string $attestation, array $evidence): void
    {
        $transfer->actions()->create(['actor_user_id' => $actor->getKey(), 'actor_name' => $actor->name, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'attestation' => $attestation, 'evidence' => $evidence]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['transfer' => $message]);
    }
}
