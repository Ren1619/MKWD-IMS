<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetEvent;
use App\Models\PropertyAccountabilityDocument;
use Brick\Math\BigDecimal;

class InventoryPropertyCardService
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array{columns:list<string>,rows:list<list<string|int|null>>}
     */
    public function build(array $filters, AssetAccountingClassification $classification): array
    {
        $assets = InventoryAsset::withTrashed()->where('accounting_classification', $classification)
            ->whereDate('acquisition_date', '<=', $filters['as_of'])
            ->when($filters['fund_cluster'] !== '', fn ($query) => $query->where('fund_cluster', $filters['fund_cluster']))
            ->when($filters['custodian'], fn ($query) => $query->whereHas('custodianAssignments', fn ($assignments) => $assignments
                ->where('hris_reference_id', $filters['custodian'])->whereDate('assigned_at', '<=', $filters['as_of'])
                ->where(fn ($end) => $end->whereNull('unassigned_at')->orWhereDate('unassigned_at', '>', $filters['as_of']))))
            ->with(['events', 'accountabilityDocuments'])->orderBy('property_number')->get();
        $rows = [];
        foreach ($assets as $asset) {
            $events = $asset->events->sortBy([['event_date', 'asc'], ['id', 'asc']])->values();
            $initial = $events->firstWhere('event_type', InventoryAssetEventType::Registered);
            $quantity = (int) data_get($initial?->details, 'property_snapshot.quantity', $asset->quantity_per_property_card);
            $quantity = max(0, $quantity);
            $initialCost = data_get($initial?->details, 'property_snapshot.acquisition_cost', $asset->acquisition_cost);
            $cost = $initialCost === null ? null : (string) $initialCost;
            $location = data_get($initial?->details, 'initial_location')
                ?? data_get($initial?->details, 'property_snapshot.location')
                ?? $events->firstWhere('event_type', InventoryAssetEventType::Transfer)->from_location
                ?? $asset->location;
            $semi = $classification === AssetAccountingClassification::SemiExpendable;
            $onHand = $semi ? ($events->contains('event_type', InventoryAssetEventType::Receipt) ? 0 : null) : $quantity;
            $expensed = false;
            $officer = null;
            $office = null;
            $documentNo = null;
            $count = null;
            $assetRows = [];
            $movedDocuments = [];
            foreach ($events as $event) {
                $date = $event->event_date->toDateString();
                if ($date > $filters['as_of']) {
                    continue;
                }
                $source = $this->documentFor($asset, $event);
                $externalTransfer = $event->event_type === InventoryAssetEventType::Transfer && data_get($event->details, 'transfer_evidence.external_ownership_transfer') === true;
                if ($source !== null) {
                    $officer = $source->recipient_name;
                    $office = $source->recipient_office;
                    $documentNo = $source->document_no;
                }
                $received = 0;
                $moved = 0;
                $disposed = 0;
                if ($event->event_type === InventoryAssetEventType::Registered && ! $semi) {
                    $received = $quantity;
                }
                if ($event->event_type === InventoryAssetEventType::Receipt) {
                    $received = $event->quantity ?? $quantity;
                    if ($semi && ! $expensed) {
                        $onHand = $received;
                    }
                }
                if (in_array($event->event_type, [InventoryAssetEventType::Assignment, InventoryAssetEventType::Transfer, InventoryAssetEventType::Issuance, InventoryAssetEventType::Reissue, InventoryAssetEventType::Return], true)) {
                    $moved = $event->quantity ?? $quantity;
                    if ($source !== null && $event->event_type !== InventoryAssetEventType::Return && ! $externalTransfer) {
                        $moved = isset($movedDocuments[$source->id]) ? 0 : $moved;
                        $movedDocuments[$source->id] = true;
                    }
                }
                if ($event->event_type === InventoryAssetEventType::Transfer) {
                    $location = $event->to_location ?? $location;
                    if ($externalTransfer) {
                        $quantity = 0;
                        $onHand = 0;
                        $officer = data_get($event->details, 'transfer_evidence.recipient.name');
                        $office = data_get($event->details, 'transfer_evidence.recipient.office');
                        $documentNo = null;
                    }
                }
                if ($event->event_type === InventoryAssetEventType::PhysicalCount) {
                    $count = $event->quantity;
                }
                if ($semi && in_array($event->event_type, [InventoryAssetEventType::Issuance, InventoryAssetEventType::Reissue], true)) {
                    $expensed = true;
                    $onHand = 0;
                }
                if (in_array($event->event_type, [InventoryAssetEventType::Disposal, InventoryAssetEventType::Derecognition], true)) {
                    $disposed = $quantity;
                    $quantity = 0;
                    $onHand = 0;
                }
                $snapshot = data_get($event->details, 'property_snapshot');
                $name = is_array($snapshot) ? ($snapshot['name'] ?? $asset->name) : ($source->asset_name ?? $asset->name);
                $number = is_array($snapshot) ? ($snapshot['property_number'] ?? $asset->property_number) : ($source->property_number ?? $asset->property_number);
                $unit = is_array($snapshot) ? ($snapshot['unit_of_measure'] ?? $asset->unit_of_measure) : ($source->unit_of_measure ?? $asset->unit_of_measure);
                $reference = $event->reference_no;
                if ($source !== null && in_array($event->event_type, [InventoryAssetEventType::Assignment, InventoryAssetEventType::Issuance, InventoryAssetEventType::Reissue, InventoryAssetEventType::IcsCreated, InventoryAssetEventType::IcsRenewed], true)) {
                    $reference = $source->document_no;
                }
                $amount = $event->amount;
                if ($received > 0 && $cost !== null) {
                    $amount = (string) BigDecimal::of($cost)->multipliedBy($received);
                }
                $basis = is_array($snapshot) ? 'Frozen event metadata' : ($source !== null ? 'Frozen accountability document' : 'Legacy event / current descriptive metadata');
                if (! $filters['from'] || $date >= $filters['from']) {
                    if (! $filters['to'] || $date <= $filters['to']) {
                        $assetRows[] = [$number, $name, $date, $reference, $externalTransfer ? 'Permanent transfer to another agency' : $event->event_type->label(), $received, $moved, $disposed, $quantity, $onHand, $unit, $cost, $amount,
                            $officer === null ? null : implode(' / ', array_filter([$officer, $office])), $location, $documentNo, $count, $event->notes, $basis];
                    }
                }
                if ($event->event_type === InventoryAssetEventType::Return) {
                    $officer = null;
                    $office = null;
                    $documentNo = null;
                }
            }
            if ($assetRows === []) {
                $hasEvidence = $events->contains(fn (InventoryAssetEvent $event): bool => $event->event_date->toDateString() <= $filters['as_of']);
                $last = $events->filter(fn (InventoryAssetEvent $event): bool => $event->event_date->toDateString() <= $filters['as_of'])->last();
                $metadata = data_get($last?->details, 'property_snapshot', []);
                $assetRows[] = [$metadata['property_number'] ?? $asset->property_number, $metadata['name'] ?? $asset->name,
                    min($filters['to'] ?? $filters['as_of'], $filters['as_of']), null, $hasEvidence ? 'Closing balance (no period movement)' : 'Legacy opening record', 0, 0, 0, $quantity, $onHand,
                    $metadata['unit_of_measure'] ?? $asset->unit_of_measure, $cost, null, $officer === null ? null : implode(' / ', array_filter([$officer, $office])),
                    $location, $documentNo, $count, $hasEvidence ? 'Balance derived from recorded transactions before this period.' : 'No lifecycle transaction evidence recorded.',
                    $hasEvidence ? 'Recorded event history' : 'Legacy current descriptive metadata'];
            }
            array_push($rows, ...$assetRows);
        }

        return ['columns' => ['Property No.', 'Description', 'Date', 'Reference', 'Transaction', 'Qty. received', 'Qty. moved (custody)', 'Qty. disposed', 'Recorded property qty.', 'Accounting inventory qty.', 'Unit', 'Unit acquisition cost', 'Recorded amount', 'Officer / office', 'Location', 'PAR / ICS reference', 'Last recorded physical count', 'Remarks', 'Evidence basis'],
            'rows' => array_map(fn (array $row): array => array_map($this->cell(...), $row), $rows)];
    }

    private function cell(mixed $value): string|int|null
    {
        if ($value === null || is_int($value) || is_string($value)) {
            return $value;
        }
        if (! is_scalar($value)) {
            throw new \LogicException('Property-card cells must be scalar recorded values.');
        }

        return (string) $value;
    }

    private function documentFor(InventoryAsset $asset, InventoryAssetEvent $event): ?PropertyAccountabilityDocument
    {
        $documents = $asset->accountabilityDocuments->filter(fn (PropertyAccountabilityDocument $document): bool => $document->issued_at->toDateString() <= $event->event_date->toDateString() && $document->created_at <= $event->created_at);
        $id = data_get($event->details, 'document_id') ?? data_get($event->details, 'replacement_document_id');
        if ($id !== null) {
            return $documents->first(fn (PropertyAccountabilityDocument $document): bool => $document->id === $id);
        }
        $exact = $documents->first(fn (PropertyAccountabilityDocument $document): bool => $document->document_no === $event->reference_no);
        if ($exact !== null) {
            return $exact;
        }
        $reference = data_get($event->details, 'hris_reference_id') ?? data_get($event->details, 'to_custodian_reference_id') ?? data_get($event->details, 'recipient_reference_id');
        if ($reference === null) {
            return null;
        }

        return $documents->filter(fn (PropertyAccountabilityDocument $document): bool => $document->recipient_reference_id === $reference
            && $document->issued_at->toDateString() <= $event->event_date->toDateString()
            && $document->created_at <= $event->created_at
        )->sortByDesc('id')->first();
    }
}
