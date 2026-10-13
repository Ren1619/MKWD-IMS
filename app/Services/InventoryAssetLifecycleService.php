<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetEvent;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryAssetLifecycleService
{
    public function __construct(private PpeDepreciationCalculator $depreciation) {}

    /** @param array<string, mixed> $data */
    public function register(array $data, User $actor): InventoryAsset
    {
        return DB::transaction(function () use ($data, $actor): InventoryAsset {
            if (filled($data['useful_life_override_reason'] ?? null)) {
                $data['useful_life_authorized_by_user_id'] = $actor->getKey();
                $data['useful_life_effective_date'] = $data['acquisition_date'];
            }
            $asset = InventoryAsset::query()->create($data);
            $this->createEvent($asset, InventoryAssetEventType::Registered, [
                'event_date' => $asset->acquisition_date->toDateString(),
                'reference_no' => $asset->inspection_acceptance_report_no
                    ?? $asset->purchase_order_no
                    ?? $asset->property_number,
                'amount' => $asset->acquisition_cost,
                'notes' => 'Property record registered.',
                'details' => [
                    'policy_version' => $asset->accounting_policy_version,
                    'capitalization_threshold' => $asset->capitalization_threshold_applied,
                    'acquisition_method' => $asset->acquisition_method,
                    'initial_location' => $asset->location,
                    'initial_condition_status' => $asset->condition_status->value,
                    'initial_lifecycle_status' => $asset->lifecycle_status->value,
                    'useful_life_months' => $asset->depreciation_useful_life_months,
                    'useful_life_override_reason' => $asset->useful_life_override_reason,
                    'useful_life_authorized_by_user_id' => $asset->useful_life_authorized_by_user_id,
                ],
            ], $actor);

            if ($asset->acceptance_date !== null) {
                $this->createEvent($asset, InventoryAssetEventType::Accepted, [
                    'event_date' => $asset->acceptance_date->toDateString(),
                    'reference_no' => $asset->inspection_acceptance_report_no,
                    'notes' => 'Inspection and acceptance recorded.',
                ], $actor);
            }

            if ($asset->accounting_classification === AssetAccountingClassification::SemiExpendable) {
                $this->createEvent($asset, InventoryAssetEventType::Receipt, [
                    'event_date' => ($asset->acceptance_date ?? $asset->acquisition_date)->toDateString(),
                    'reference_no' => $asset->inspection_acceptance_report_no
                        ?? $asset->purchase_order_no
                        ?? $asset->property_number,
                    'amount' => $asset->acquisition_cost,
                    'quantity' => 1,
                    'notes' => 'Semi-expendable property received into Property Unit custody and remains on hand before issuance.',
                    'details' => ['value_class' => $asset->semi_expendable_value_class->value],
                ], $actor);
            }

            return $asset->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, InventoryAsset>
     */
    public function registerBatch(array $data, User $actor): Collection
    {
        $quantity = (int) ($data['registration_quantity'] ?? 1);
        $prefix = $data['property_number_prefix'] ?? null;
        unset($data['registration_quantity'], $data['property_number_prefix']);

        if ($quantity === 1) {
            return collect([$this->register($data, $actor)]);
        }

        $propertyNumbers = collect(range(1, $quantity))
            ->map(fn (int $sequence): string => sprintf('%s-%04d', $prefix, $sequence));

        if (InventoryAsset::query()->whereIn('property_number', $propertyNumbers)->exists()) {
            throw ValidationException::withMessages([
                'property_number_prefix' => 'The generated property-number range conflicts with an existing record.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $propertyNumbers): Collection {
            return $propertyNumbers->map(function (string $propertyNumber) use ($data, $actor): InventoryAsset {
                return $this->register([
                    ...$data,
                    'serial_number' => null,
                    'property_number' => $propertyNumber,
                    'quantity_per_property_card' => 1,
                    'quantity_per_physical_count' => 1,
                ], $actor);
            });
        });
    }

    /** @param array<string, mixed> $data */
    public function record(InventoryAsset $asset, array $data, User $actor): InventoryAssetEvent
    {
        return DB::transaction(function () use ($asset, $data, $actor): InventoryAssetEvent {
            $lockedAsset = InventoryAsset::query()
                ->whereKey($asset->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAsset->accounting_classification !== AssetAccountingClassification::Ppe) {
                throw new InvalidArgumentException('PPE lifecycle transactions can only be recorded in the PPE module.');
            }

            $type = InventoryAssetEventType::from($data['event_type']);
            if ($type === InventoryAssetEventType::Transfer && $lockedAsset->current_custodian_reference_id !== null) {
                throw new InvalidArgumentException('Use an approved PTR with handover evidence to relocate assigned property.');
            }
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = $lockedAsset->events()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            if (
                $lockedAsset->accounting_status === AssetAccountingStatus::Derecognized
                || ($lockedAsset->derecognized_at && $lockedAsset->derecognized_at->lte($data['event_date']))
            ) {
                throw new InvalidArgumentException('No new lifecycle transaction can be posted after derecognition.');
            }

            $this->assertTransactionAllowed($lockedAsset, $type, $data);
            $event = $this->createEvent($lockedAsset, $type, [
                'event_date' => $data['event_date'],
                'reference_no' => $data['reference_no'],
                'amount' => $data['amount'] ?? ($data['proceeds'] ?? null),
                'quantity' => $data['quantity'] ?? null,
                'from_location' => $type === InventoryAssetEventType::Transfer ? $lockedAsset->location : null,
                'to_location' => $data['to_location'] ?? null,
                'condition_status' => $data['condition_status'] ?? null,
                'notes' => $data['notes'] ?? ($data['circumstances'] ?? null),
                'details' => Arr::only($data, [
                    'remaining_useful_life_months',
                    'vendor',
                    'downtime_hours',
                    'parts_used',
                    'recommendations',
                    'counted_by',
                    'count_location',
                    'impairment_trigger',
                    'assessment_basis',
                    'loss_type',
                    'circumstances',
                    'recovery_status',
                    'relief_status',
                    'liability_status',
                    'disposal_method',
                    'disposal_evidence',
                    'approval_no',
                    'approval_date',
                    'proceeds',
                    'appraised_value',
                    'recipient',
                ]),
                'idempotency_key' => $idempotencyKey,
            ], $actor);

            $this->applyCurrentSnapshot($lockedAsset, $event, $data, $actor);

            return $event;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function recordSystemEvent(
        InventoryAsset $asset,
        InventoryAssetEventType $type,
        array $data,
        ?User $actor,
    ): InventoryAssetEvent {
        return $this->createEvent($asset, $type, $data, $actor);
    }

    /** @param array<string, mixed> $data */
    public function updateAccounting(InventoryAsset $asset, array $data, User $actor): InventoryAsset
    {
        return DB::transaction(function () use ($asset, $data, $actor): InventoryAsset {
            $lockedAsset = InventoryAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $fields = [
                'available_for_use_date',
                'residual_value_percentage',
                'residual_value_basis',
                'depreciation_useful_life_months',
            ];
            $before = $lockedAsset->only($fields);

            $lockedAsset->update($data);
            $this->createEvent($lockedAsset, InventoryAssetEventType::AccountingPolicyChanged, [
                'event_date' => today()->toDateString(),
                'reference_no' => 'ACCOUNTING-ESTIMATE',
                'notes' => $data['residual_value_basis'],
                'details' => [
                    'before' => $before,
                    'after' => $lockedAsset->fresh()->only($fields),
                ],
            ], $actor);

            return $lockedAsset->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function assertTransactionAllowed(
        InventoryAsset $asset,
        InventoryAssetEventType $type,
        array $data,
    ): void {
        if ($type === InventoryAssetEventType::Impairment) {
            $asset->load('events');
            $carryingAmount = BigDecimal::of($this->depreciation->calculate($asset, $data['event_date'])->carryingAmount);

            if (BigDecimal::of((string) $data['amount'])->isGreaterThan($carryingAmount)) {
                throw new InvalidArgumentException('The impairment loss cannot exceed the carrying amount on the event date.');
            }
        }

        if ($type === InventoryAssetEventType::ImpairmentReversal) {
            $asset->load('events');
            $recognizedLosses = BigDecimal::of($this->depreciation->calculate($asset, $data['event_date'])->impairmentLosses);

            if (BigDecimal::of((string) $data['amount'])->isGreaterThan($recognizedLosses)) {
                throw new InvalidArgumentException('The reversal cannot exceed impairment losses recognized as of the event date.');
            }
        }

        if ($type === InventoryAssetEventType::Disposal) {
            if ($asset->determineCustodyStatus() !== AssetCustodyStatus::Available) {
                throw new InvalidArgumentException('Return all borrowings and accountability before disposal.');
            }

            if (
                $asset->condition_status !== AssetConditionStatus::NonUsable
                && ! in_array($asset->lifecycle_status, [AssetLifecycleStatus::Retired, AssetLifecycleStatus::ForDisposal], true)
            ) {
                throw new InvalidArgumentException('The property must first be declared unserviceable or for disposal.');
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function applyCurrentSnapshot(
        InventoryAsset $asset,
        InventoryAssetEvent $event,
        array $data,
        User $actor,
    ): void {
        match ($event->event_type) {
            InventoryAssetEventType::Transfer => $asset->update(['location' => $data['to_location']]),
            InventoryAssetEventType::PhysicalCount => $asset->update([
                'quantity_per_physical_count' => $data['quantity'],
                'condition_status' => $data['condition_status'],
                'physical_count_remarks' => $data['notes'] ?? null,
            ]),
            InventoryAssetEventType::Impairment,
            InventoryAssetEventType::ImpairmentReversal => $this->updateImpairmentSnapshot($asset),
            InventoryAssetEventType::Loss => $asset->update([
                'lifecycle_status' => AssetLifecycleStatus::Lost,
                'loss_report_no' => $data['reference_no'],
                'loss_report_date' => $data['event_date'],
                'loss_type' => $data['loss_type'],
                'loss_circumstances' => $data['circumstances'],
            ]),
            InventoryAssetEventType::Unserviceable => $asset->update([
                'lifecycle_status' => AssetLifecycleStatus::ForDisposal,
                'condition_status' => AssetConditionStatus::NonUsable,
                'appraised_value' => $data['appraised_value'] ?? $asset->appraised_value,
                'appraisal_date' => isset($data['appraised_value']) ? $data['event_date'] : $asset->appraisal_date,
            ]),
            InventoryAssetEventType::Disposal => $this->derecognize($asset, $event, $data, $actor),
            default => null,
        };
    }

    private function updateImpairmentSnapshot(InventoryAsset $asset): void
    {
        $losses = BigDecimal::of((string) $asset->events()
            ->where('event_type', InventoryAssetEventType::Impairment->value)
            ->sum('amount'));
        $reversals = BigDecimal::of((string) $asset->events()
            ->where('event_type', InventoryAssetEventType::ImpairmentReversal->value)
            ->sum('amount'));

        $asset->update(['impairment_losses' => (string) $losses->minus($reversals)]);
    }

    /** @param array<string, mixed> $data */
    private function derecognize(
        InventoryAsset $asset,
        InventoryAssetEvent $disposal,
        array $data,
        User $actor,
    ): void {
        $asset->update([
            'lifecycle_status' => AssetLifecycleStatus::Disposed,
            'accounting_status' => AssetAccountingStatus::Derecognized,
            'derecognized_at' => $data['event_date'],
            'disposal_method' => $data['disposal_method'],
            'disposal_value' => $data['proceeds'],
            'appraised_value' => $data['appraised_value'] ?? $asset->appraised_value,
            'appraisal_date' => isset($data['appraised_value']) ? $data['event_date'] : $asset->appraisal_date,
        ]);

        $this->createEvent($asset, InventoryAssetEventType::Derecognition, [
            'event_date' => $data['event_date'],
            'reference_no' => $data['approval_no'],
            'notes' => 'PPE removed from the accounts following approved disposal.',
            'details' => [
                'disposal_event_id' => $disposal->getKey(),
                'approval_date' => $data['approval_date'],
            ],
        ], $actor);
    }

    /** @param array<string, mixed> $data */
    private function createEvent(
        InventoryAsset $asset,
        InventoryAssetEventType $type,
        array $data,
        ?User $actor,
    ): InventoryAssetEvent {
        return $asset->events()->create([
            ...$data,
            'details' => [
                ...($data['details'] ?? []),
                'property_snapshot' => [
                    'property_number' => $asset->property_number,
                    'name' => $asset->name,
                    'unit_of_measure' => $asset->unit_of_measure,
                    'quantity' => $asset->quantity_per_property_card,
                    'acquisition_cost' => $asset->acquisition_cost,
                    'acquisition_date' => $asset->acquisition_date?->toDateString(),
                    'serial_number' => $asset->serial_number,
                    'fund_cluster' => $asset->fund_cluster,
                    'accounting_classification' => $asset->accounting_classification->value,
                    'condition_status' => $asset->condition_status->value,
                    'location' => $asset->location,
                ],
            ],
            'event_type' => $type,
            'recorded_by_user_id' => $actor?->getKey(),
        ]);
    }
}
