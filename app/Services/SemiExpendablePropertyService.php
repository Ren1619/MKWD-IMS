<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetEvent;
use App\Models\User;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableReliefStatus;
use App\SemiExpendableValueClass;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SemiExpendablePropertyService
{
    public function __construct(private InventoryAssetLifecycleService $lifecycle) {}

    /** @param array<string, mixed> $data */
    public function record(InventoryAsset $asset, array $data, User $actor): InventoryAssetEvent
    {
        return DB::transaction(function () use ($asset, $data, $actor): InventoryAssetEvent {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $this->assertSemiExpendable($lockedAsset);

            if ($lockedAsset->physical_status === SemiExpendablePhysicalStatus::Disposed || $lockedAsset->lifecycle_status === AssetLifecycleStatus::TransferredOut) {
                throw ValidationException::withMessages(['event_type' => 'Disposed or permanently transferred property cannot receive another lifecycle transaction.']);
            }

            $type = InventoryAssetEventType::from($data['event_type']);
            if ($type === InventoryAssetEventType::Transfer && $lockedAsset->current_custodian_reference_id !== null) {
                throw ValidationException::withMessages(['event_type' => 'Use an approved PTR with handover evidence to relocate assigned property.']);
            }
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = $lockedAsset->events()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $this->assertAllowed($lockedAsset, $type);
            $event = $this->lifecycle->recordSystemEvent($lockedAsset, $type, [
                'event_date' => $data['event_date'],
                'reference_no' => $data['reference_no'],
                'amount' => $data['amount'] ?? ($data['proceeds'] ?? null),
                'quantity' => $data['quantity'] ?? null,
                'from_location' => $type === InventoryAssetEventType::Transfer ? $lockedAsset->location : null,
                'to_location' => $data['to_location'] ?? null,
                'condition_status' => $data['condition_status'] ?? null,
                'notes' => $data['notes'] ?? ($data['circumstances'] ?? null),
                'details' => Arr::only($data, [
                    'counted_by', 'count_location', 'loss_type', 'circumstances', 'recovery_status',
                    'incident_date', 'date_discovered', 'security_reference',
                    'relief_status', 'liability_status', 'disposal_method', 'approval_no', 'approval_date',
                    'proceeds', 'appraised_value', 'recipient', 'received_by', 'approving_authority',
                    'disposal_evidence',
                ]),
                'idempotency_key' => $idempotencyKey,
            ], $actor);

            $this->applySnapshot($lockedAsset, $event, $data, $actor);

            return $event;
        }, attempts: 3);
    }

    public function expireDueLowValueAccountabilities(Carbon|string|null $asOf = null): int
    {
        $date = Carbon::parse($asOf ?? today())->startOfDay();
        $expired = 0;

        InventoryAsset::query()
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->where('semi_expendable_value_class', SemiExpendableValueClass::Low)
            ->whereIn('accountability_status', [
                SemiExpendableAccountabilityStatus::PendingRecipient,
                SemiExpendableAccountabilityStatus::Accountable,
            ])
            ->whereDate('accountability_expires_at', '<=', $date)
            ->lazyById(100, column: 'inventory_asset_id')
            ->each(function (InventoryAsset $asset) use ($date, &$expired): void {
                DB::transaction(function () use ($asset, $date, &$expired): void {
                    $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

                    if ($lockedAsset->semi_expendable_value_class !== SemiExpendableValueClass::Low
                        || ! in_array($lockedAsset->accountability_status, [
                            SemiExpendableAccountabilityStatus::PendingRecipient,
                            SemiExpendableAccountabilityStatus::Accountable,
                        ], true)
                        || $lockedAsset->accountability_expires_at?->gt($date)) {
                        return;
                    }

                    $lockedAsset->accountabilityDocuments()
                        ->where('document_type', 'ICS')
                        ->whereIn('status', ['pending_recipient', 'active'])
                        ->update([
                            'status' => 'expired',
                            'closed_at' => $date,
                            'closure_reason' => 'Low-valued accountability reached its configured useful-life expiration.',
                        ]);
                    $lockedAsset->update(['accountability_status' => SemiExpendableAccountabilityStatus::AccountabilityExtinguished]);
                    $this->lifecycle->recordSystemEvent($lockedAsset, InventoryAssetEventType::AccountabilityExpired, [
                        'event_date' => $date->toDateString(),
                        'reference_no' => null,
                        'notes' => 'Low-valued accountability automatically extinguished at useful-life expiration; no disposal was recorded.',
                    ], null);
                    $expired++;
                });
            });

        return $expired;
    }

    /** @return array<string, mixed> */
    public function stateAsOf(InventoryAsset $asset, Carbon|string $asOf): array
    {
        $date = Carbon::parse($asOf)->endOfDay();
        $events = $asset->events()->whereDate('event_date', '<=', $date)->orderBy('event_date')->orderBy('id')->get();
        $document = $asset->accountabilityDocuments()->where('issued_at', '<=', $date)->latest('issued_at')->first();
        $assignment = $asset->custodianAssignments()
            ->where('assigned_at', '<=', $date)
            ->where(fn ($query) => $query->whereNull('unassigned_at')->orWhere('unassigned_at', '>', $date))
            ->with('reference:id,name,code')
            ->latest('assigned_at')
            ->first();

        return [
            'as_of' => $date->toDateString(),
            'custodian' => $assignment?->reference?->name,
            'ics_number' => $document?->document_no,
            'accountability_status' => $document->status ?? SemiExpendableAccountabilityStatus::Unassigned->value,
            'physical_status' => $this->physicalStatusFromEvents($events),
            'location' => $events->where('event_type', InventoryAssetEventType::Transfer)->last()->to_location
                ?? $events->firstWhere('event_type', InventoryAssetEventType::Registered)?->details['initial_location']
                ?? $asset->location,
            'value_class' => $asset->semi_expendable_value_class?->value,
        ];
    }

    private function assertSemiExpendable(InventoryAsset $asset): void
    {
        if ($asset->accounting_classification !== AssetAccountingClassification::SemiExpendable) {
            throw ValidationException::withMessages(['event_type' => 'This transaction is only available for semi-expendable property.']);
        }
    }

    private function assertAllowed(InventoryAsset $asset, InventoryAssetEventType $type): void
    {
        $allowed = [
            InventoryAssetEventType::Transfer,
            InventoryAssetEventType::Maintenance,
            InventoryAssetEventType::PhysicalCount,
            InventoryAssetEventType::Loss,
            InventoryAssetEventType::ReliefRequested,
            InventoryAssetEventType::ReliefApproved,
            InventoryAssetEventType::ReliefDenied,
            InventoryAssetEventType::Unserviceable,
            InventoryAssetEventType::Disposal,
        ];

        if (! in_array($type, $allowed, true)) {
            throw ValidationException::withMessages(['event_type' => 'This is not a supported semi-expendable transaction.']);
        }

        if (in_array($type, [InventoryAssetEventType::ReliefRequested, InventoryAssetEventType::ReliefApproved, InventoryAssetEventType::ReliefDenied], true)
            && ! $asset->events()->where('event_type', InventoryAssetEventType::Loss)->exists()) {
            throw ValidationException::withMessages(['event_type' => 'A loss report must exist before relief can be processed.']);
        }

        if ($type === InventoryAssetEventType::ReliefApproved && $asset->relief_status !== SemiExpendableReliefStatus::Pending) {
            throw ValidationException::withMessages(['event_type' => 'Only a pending relief request may be approved.']);
        }

        if ($type === InventoryAssetEventType::Disposal
            && ($asset->current_custodian_reference_id !== null || $asset->physical_status !== SemiExpendablePhysicalStatus::ForDisposal)) {
            throw ValidationException::withMessages(['event_type' => 'Return the property and declare it unserviceable before disposal.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function applySnapshot(InventoryAsset $asset, InventoryAssetEvent $event, array $data, User $actor): void
    {
        match ($event->event_type) {
            InventoryAssetEventType::Transfer => $asset->update([
                'location' => $data['to_location'],
                'physical_status' => $asset->current_custodian_reference_id
                    ? SemiExpendablePhysicalStatus::Transferred
                    : SemiExpendablePhysicalStatus::InPropertyCustody,
            ]),
            InventoryAssetEventType::PhysicalCount => $asset->update([
                'quantity_per_physical_count' => $data['quantity'],
                'condition_status' => $data['condition_status'],
                'physical_count_remarks' => $data['notes'] ?? null,
            ]),
            InventoryAssetEventType::Loss => $this->applyIncident($asset, $data),
            InventoryAssetEventType::ReliefRequested => $asset->update([
                'relief_status' => SemiExpendableReliefStatus::Pending,
                'accountability_status' => SemiExpendableAccountabilityStatus::PendingRelief,
            ]),
            InventoryAssetEventType::ReliefApproved => $this->approveRelief($asset, $data, $actor),
            InventoryAssetEventType::ReliefDenied => $asset->update([
                'relief_status' => SemiExpendableReliefStatus::Denied,
                'accountability_status' => SemiExpendableAccountabilityStatus::Accountable,
            ]),
            InventoryAssetEventType::Unserviceable => $asset->update([
                'lifecycle_status' => AssetLifecycleStatus::ForDisposal,
                'physical_status' => SemiExpendablePhysicalStatus::ForDisposal,
                'condition_status' => AssetConditionStatus::NonUsable,
                'available_for_reissue' => false,
                'appraised_value' => $data['appraised_value'] ?? $asset->appraised_value,
                'appraisal_date' => isset($data['appraised_value']) ? $data['event_date'] : $asset->appraisal_date,
            ]),
            InventoryAssetEventType::Disposal => $asset->update([
                'lifecycle_status' => AssetLifecycleStatus::Disposed,
                'physical_status' => SemiExpendablePhysicalStatus::Disposed,
                'accountability_status' => SemiExpendableAccountabilityStatus::AccountabilityExtinguished,
                'accounting_status' => AssetAccountingStatus::Disposed,
                'derecognized_at' => $data['event_date'],
                'disposal_method' => $data['disposal_method'],
                'disposal_value' => $data['proceeds'],
                'available_for_reissue' => false,
            ]),
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    private function approveRelief(InventoryAsset $asset, array $data, User $actor): void
    {
        $reason = $data['notes'] ?? 'Approved relief from property accountability.';
        $asset->accountabilityDocuments()->whereIn('status', ['pending_recipient', 'active'])->get()
            ->each(function ($document) use ($actor, $reason): void {
                $previousStatus = $document->status;
                $document->update([
                    'status' => 'relieved',
                    'closed_at' => now(),
                    'closure_reason' => $reason,
                ]);
                $document->actions()->create([
                    'actor_user_id' => $actor->getKey(),
                    'action' => 'relief_approved',
                    'from_status' => $previousStatus,
                    'to_status' => 'relieved',
                    'attestation' => 'I certify that relief was approved by the authorized authority identified in the lifecycle record.',
                    'remarks' => $reason,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        $asset->custodianAssignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
        $asset->update([
            'current_custodian_reference_id' => null,
            'relief_status' => SemiExpendableReliefStatus::Approved,
            'accountability_status' => SemiExpendableAccountabilityStatus::Relieved,
        ]);
        $this->lifecycle->recordSystemEvent($asset, InventoryAssetEventType::AccountabilityExtinguished, [
            'event_date' => $data['event_date'],
            'reference_no' => $data['reference_no'],
            'notes' => 'Accountability extinguished only after approved relief.',
        ], $actor);
    }

    /** @param array<string, mixed> $data */
    private function applyIncident(InventoryAsset $asset, array $data): void
    {
        $incidentType = $data['loss_type'];
        $snapshot = [
            'loss_report_no' => $data['reference_no'],
            'loss_report_date' => $data['event_date'],
            'incident_reported_at' => $data['event_date'],
            'loss_type' => $incidentType,
            'loss_circumstances' => $data['circumstances'],
            'relief_status' => SemiExpendableReliefStatus::None,
        ];

        if (in_array($incidentType, ['lost', 'stolen'], true)) {
            $snapshot['lifecycle_status'] = AssetLifecycleStatus::Lost;
            $snapshot['physical_status'] = SemiExpendablePhysicalStatus::Missing;
        } elseif ($incidentType === 'damaged') {
            $snapshot['condition_status'] = AssetConditionStatus::Defective;
        } else {
            $snapshot['lifecycle_status'] = AssetLifecycleStatus::ForDisposal;
            $snapshot['physical_status'] = SemiExpendablePhysicalStatus::ForDisposal;
            $snapshot['condition_status'] = AssetConditionStatus::NonUsable;
        }

        $asset->update($snapshot);
    }

    /** @param Collection<int, InventoryAssetEvent> $events */
    private function physicalStatusFromEvents($events): string
    {
        $last = $events->last();

        return match ($last?->event_type) {
            InventoryAssetEventType::Disposal => SemiExpendablePhysicalStatus::Disposed->value,
            InventoryAssetEventType::Loss => SemiExpendablePhysicalStatus::Missing->value,
            InventoryAssetEventType::Unserviceable => SemiExpendablePhysicalStatus::ForDisposal->value,
            InventoryAssetEventType::Return => SemiExpendablePhysicalStatus::Returned->value,
            InventoryAssetEventType::Issuance, InventoryAssetEventType::Reissue => SemiExpendablePhysicalStatus::Issued->value,
            default => SemiExpendablePhysicalStatus::InPropertyCustody->value,
        };
    }
}
