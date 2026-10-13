<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryDisposal;
use App\Models\InventoryDisposalLine;
use App\Models\User;
use App\SemiExpendablePhysicalStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryDisposalService
{
    public function __construct(private InventoryAssetLifecycleService $ppe, private SemiExpendablePropertyService $semi) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, User $actor): InventoryDisposal
    {
        abort_unless($actor->can('manage-inventory'), 403);

        return DB::transaction(function () use ($data, $actor): InventoryDisposal {
            $ids = array_values(array_filter(array_column($data['lines'], 'inventory_asset_id')));
            if (count(array_unique($ids)) !== count($ids)) {
                $this->fail('A registered property may appear only once in a WMR.');
            }
            $assets = $this->lockAssets($ids);
            $certifier = $this->person($data['certifier_reference_id']);
            $document = InventoryDisposal::query()->create([
                'place_of_storage' => $data['place_of_storage'], 'method' => $data['method'], 'reason' => $data['reason'],
                'created_by_user_id' => $actor->getKey(), 'certifier_snapshot' => $certifier,
                'certification_reference' => $data['certification_reference'], 'certification_attestation' => $data['certification_attestation'],
            ]);
            $document->update(['document_no' => sprintf('WMR-%s-%06d', today()->format('Y'), $document->getKey())]);
            foreach ($data['lines'] as $input) {
                $asset = filled($input['inventory_asset_id'] ?? null) ? $assets->get($input['inventory_asset_id']) : null;
                if (filled($input['inventory_asset_id'] ?? null) && $asset === null) {
                    $this->fail('A selected property is no longer available.');
                }
                if ($asset !== null) {
                    $this->assertDisposable($asset);
                    if ((int) $input['quantity'] !== $asset->quantity_per_property_card) {
                        $this->fail('Registered disposal must cover the whole property-card quantity. Use a standalone waste line for a removed component.');
                    }
                    if (InventoryDisposalLine::query()->where('inventory_asset_id', $asset->getKey())->whereHas('document', fn ($query) => $query->whereIn('status', ['submitted', 'approved']))->exists()) {
                        $this->fail('A selected property already has an open WMR.');
                    }
                }
                $snapshot = $asset !== null ? $this->snapshot($asset) : null;
                $document->lines()->create([
                    'inventory_asset_id' => $asset?->getKey(), 'quantity' => $input['quantity'],
                    'unit' => $asset !== null ? $asset->unit_of_measure : $input['unit'], 'description' => $asset !== null ? $asset->name : $input['description'],
                    'source_reference' => $input['source_reference'], 'source_snapshot' => $snapshot,
                    'source_hash' => $snapshot !== null ? $this->hash($snapshot) : null,
                ]);
            }
            $this->record($document, $actor, 'submit', null, 'submitted', $data['certification_attestation'], ['certification_reference' => $data['certification_reference']]);

            return $document;
        });
    }

    /** @return list<string> */
    public function allowedActions(InventoryDisposal $document, User $actor): array
    {
        if (! $actor->can('manage-inventory')) {
            return [];
        }

        return match ($document->status) {
            'submitted' => $actor->getKey() !== $document->created_by_user_id ? ['approve', 'reject', 'cancel'] : ['cancel'],
            'approved' => ['complete', 'cancel'],
            default => [],
        };
    }

    /** @param array<string,mixed> $data */
    public function transition(InventoryDisposal $document, array $data, User $actor): InventoryDisposal
    {
        abort_unless($actor->can('manage-inventory'), 403);

        return DB::transaction(function () use ($document, $data, $actor): InventoryDisposal {
            $ids = $document->lines()->whereNotNull('inventory_asset_id')->pluck('inventory_asset_id')->all();
            $assets = $this->lockAssets($ids);
            $locked = InventoryDisposal::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail()->load('lines');
            $action = $data['action'];
            if (! in_array($action, $this->allowedActions($locked, $actor), true)) {
                $this->fail('This WMR decision is not permitted for the current stage or actor.');
            }
            $from = $locked->status;
            $evidence = $data;
            if (in_array($action, ['cancel', 'reject'], true)) {
                $to = $action === 'cancel' ? 'cancelled' : 'rejected';
            } else {
                foreach ($locked->lines as $line) {
                    if ($line->inventory_asset_id !== null) {
                        $asset = $assets->get($line->inventory_asset_id);
                        if ($asset === null) {
                            $this->fail('A linked property is no longer available.');
                        }
                        $this->assertDisposable($asset);
                        if ($this->hash($this->snapshot($asset)) !== $line->source_hash) {
                            $this->fail('Property or lifecycle evidence changed. Cancel and prepare a corrected WMR.');
                        }
                    }
                }
                if ($action === 'approve') {
                    $chair = $this->person($data['chair_reference_id']);
                    if ($chair['id'] === $locked->certifier_snapshot['id']) {
                        $this->fail('The certifying custodian cannot also be the approving committee chairperson.');
                    }
                    $evidence['chair'] = $chair;
                    $to = 'approved';
                } else {
                    $approval = $locked->actions()->where('action', 'approve')->sole();
                    if ($data['disposition_date'] < $approval->evidence['approval_date'] || $data['disposition_date'] > today()->toDateString()) {
                        $this->fail('Physical disposition must follow the recorded approval date and cannot be future dated.');
                    }
                    $people = [$data['inspector_reference_id'], $data['internal_control_reference_id'], $data['accounting_witness_reference_id']];
                    if (count(array_unique($people)) !== 3) {
                        $this->fail('The inspector and the two disposition witnesses must be different people.');
                    }
                    $evidence['inspector'] = $this->person($people[0]);
                    $evidence['internal_control_witness'] = $this->person($people[1]);
                    $evidence['accounting_witness'] = $this->person($people[2]);
                    if (! is_array($data['lines'] ?? null)) {
                        $this->fail('Completion requires the WMR lines.');
                    }
                    $posted = collect($data['lines'])->keyBy('id');
                    if ($posted->count() !== $locked->lines->count() || $locked->lines->contains(fn (InventoryDisposalLine $line): bool => ! $posted->has($line->getKey()))) {
                        $this->fail('Completion must include exactly the lines belonging to this WMR.');
                    }
                    foreach ($locked->lines as $line) {
                        $input = $posted->get($line->getKey());
                        $amount = BigDecimal::of((string) $input['proceeds'])->toScale(2);
                        if ($locked->method === 'destroyed' && ! $amount->isZero()) {
                            $this->fail('Destroyed waste must have zero sale proceeds.');
                        }
                        if ($locked->method !== 'destroyed' && ($amount->isLessThanOrEqualTo(0) || blank($input['or_number'] ?? null))) {
                            $this->fail('Sales require positive proceeds and an official receipt for each line.');
                        }
                        $event = null;
                        if ($line->inventory_asset_id !== null) {
                            $asset = $assets->get($line->inventory_asset_id);
                            if ($asset === null) {
                                $this->fail('A linked property is no longer available.');
                            }
                            $payload = [
                                'event_type' => InventoryAssetEventType::Disposal->value, 'event_date' => today()->toDateString(),
                                'reference_no' => $locked->document_no, 'disposal_method' => $locked->method,
                                'approval_no' => $approval->evidence['approval_no'], 'approval_date' => $approval->evidence['approval_date'],
                                'proceeds' => (string) $amount, 'recipient' => $data['disposition_reference'],
                                'notes' => $data['attestation'],
                                'disposal_evidence' => [
                                    'wmr_id' => $locked->getKey(), 'wmr_no' => $locked->document_no, 'disposition_date' => $data['disposition_date'],
                                    'disposition_reference' => $data['disposition_reference'], 'accounting_reference' => $data['accounting_reference'],
                                    'inspection_certificate_reference' => $data['inspection_certificate_reference'],
                                    'internal_control_signature_reference' => $data['internal_control_signature_reference'],
                                    'accounting_signature_reference' => $data['accounting_signature_reference'],
                                    'or_number' => $input['or_number'] ?? null, 'inspector' => $evidence['inspector'],
                                    'internal_control_witness' => $evidence['internal_control_witness'], 'accounting_witness' => $evidence['accounting_witness'],
                                ],
                            ];
                            try {
                                $event = $asset->accounting_classification === AssetAccountingClassification::Ppe
                                    ? $this->ppe->record($asset, $payload, $actor)
                                    : $this->semi->record($asset, $payload, $actor);
                            } catch (\InvalidArgumentException $exception) {
                                $this->fail($exception->getMessage());
                            }
                        }
                        $line->update(['proceeds' => (string) $amount, 'or_number' => $input['or_number'] ?? null, 'disposal_event_id' => $event?->getKey()]);
                    }
                    $locked->update(['completed_at' => now()]);
                    $to = 'completed';
                }
            }
            $locked->update(['status' => $to]);
            $this->record($locked, $actor, $action, $from, $to, $data['attestation'], $evidence);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int,int|string>  $ids
     * @return Collection<int|string,InventoryAsset>
     */
    private function lockAssets(array $ids): Collection
    {
        return InventoryAsset::withTrashed()->whereKey($ids)->orderBy('inventory_asset_id')->lockForUpdate()->get()->keyBy('inventory_asset_id');
    }

    private function assertDisposable(InventoryAsset $asset): void
    {
        if ($asset->trashed() || $asset->current_custodian_reference_id !== null || $asset->activeBorrowing()->exists()
            || $asset->accountabilityDocuments()->whereIn('status', ['active', 'pending_recipient'])->exists()
            || in_array($asset->lifecycle_status, [AssetLifecycleStatus::Disposed, AssetLifecycleStatus::Lost, AssetLifecycleStatus::TransferredOut], true)
            || $asset->accounting_status === AssetAccountingStatus::Derecognized
            || ($asset->condition_status !== AssetConditionStatus::NonUsable && ! in_array($asset->lifecycle_status, [AssetLifecycleStatus::ForDisposal, AssetLifecycleStatus::Retired], true))
            || ($asset->accounting_classification === AssetAccountingClassification::SemiExpendable && $asset->physical_status !== SemiExpendablePhysicalStatus::ForDisposal)) {
            $this->fail('Linked property must be returned, free of open accountability/borrowing, and declared unserviceable or for disposal. A replaced component must be a standalone waste line.');
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(InventoryAsset $asset): array
    {
        return [...array_intersect_key($asset->getAttributes(), array_flip(['property_number', 'serial_number', 'name', 'description', 'quantity_per_property_card', 'unit_of_measure', 'location', 'acquisition_cost', 'acquisition_date', 'accounting_classification', 'condition_status', 'lifecycle_status', 'accounting_status', 'appraised_value', 'physical_status'])),
            'latest_event_id' => $asset->events()->max('id')];
    }

    /** @param array<string,mixed> $snapshot */
    private function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    private function person(int|string $id): array
    {
        $reference = HrisReference::query()->whereKey($id)->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->firstOrFail();

        return ['id' => $reference->getKey(), 'name' => $reference->name, 'code' => $reference->code, 'position' => data_get($reference->metadata, 'position.name'), 'office' => $reference->officeName()];
    }

    /** @param array<string,mixed> $evidence */
    private function record(InventoryDisposal $document, User $actor, string $action, ?string $from, string $to, string $attestation, array $evidence): void
    {
        $document->actions()->create(['actor_user_id' => $actor->getKey(), 'actor_name' => $actor->name, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'attestation' => $attestation, 'evidence' => $evidence]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['disposal' => $message]);
    }
}
