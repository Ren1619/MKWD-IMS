<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\InventoryAssetEventType;
use App\Models\InventoryAssetEvent;
use App\Models\PropertyAccountabilityDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryTransferReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{columns: list<string>, rows: list<list<string|int|float|null>>, summary: array<string, string|int>}
     */
    public function build(array $filters, ?AssetAccountingClassification $classification = null): array
    {
        $events = InventoryAssetEvent::query()
            ->where('event_type', InventoryAssetEventType::Transfer)
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('event_date', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('event_date', '<=', $date))
            ->with(['asset' => fn (Relation $query) => $query->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->with('accountabilityDocuments')])
            ->orderBy('event_date')->orderBy('id')->get();
        $rows = [];
        foreach ($events as $event) {
            $asset = $event->asset;
            if ($asset === null) {
                continue;
            }
            $source = $this->document($event, 'superseded_document_id');
            $destination = $this->document($event, 'replacement_document_id');
            $custodyTransfer = isset($event->details['superseded_document_id']) || isset($event->details['replacement_document_id']);
            if (! $custodyTransfer) {
                $source = $this->latestDocument($event);
                $destination = $source;
            }
            $propertyDocument = $destination ?? $source;
            $recordedClassification = data_get($event->details, 'property_snapshot.accounting_classification')
                ?? ($propertyDocument !== null ? ($propertyDocument->document_type === 'PAR' ? AssetAccountingClassification::Ppe->value : AssetAccountingClassification::SemiExpendable->value) : $asset->accounting_classification->value);
            if ($classification !== null && $classification->value !== $recordedClassification) {
                continue;
            }
            $fund = data_get($event->details, 'property_snapshot.fund_cluster', $propertyDocument->fund_cluster ?? $asset->fund_cluster);
            if (filled($filters['fund_cluster'] ?? null) && $fund !== $filters['fund_cluster']) {
                continue;
            }
            $custodian = $filters['custodian'] ?? null;
            if ($custodian !== null && (int) $custodian !== $source?->recipient_reference_id && (int) $custodian !== $destination?->recipient_reference_id) {
                continue;
            }
            $snapshot = data_get($event->details, 'property_snapshot');
            $hasSnapshot = is_array($snapshot);
            $transferEvidence = data_get($event->details, 'transfer_evidence');
            $rows[] = [
                $event->reference_no, $event->event_date->toDateString(),
                $this->cell(data_get($transferEvidence, 'transfer_type')) ?? ($custodyTransfer ? 'Reassignment' : 'Location transfer'),
                $this->cell(data_get($snapshot, 'acquisition_date', $propertyDocument?->acquisition_date?->toDateString())),
                $this->cell(data_get($snapshot, 'property_number', $propertyDocument->property_number ?? $asset->property_number)),
                $this->cell(data_get($snapshot, 'name', $propertyDocument->asset_name ?? $asset->name)),
                $this->cell(data_get($snapshot, 'serial_number', $propertyDocument?->serial_number)),
                $this->cell(data_get($snapshot, 'acquisition_cost', $propertyDocument?->acquisition_cost)),
                $event->condition_status->value ?? $this->cell(data_get($snapshot, 'condition_status')) ?? 'Not recorded',
                $source->recipient_name ?? 'Not recorded', $source?->recipient_office,
                $this->cell(data_get($transferEvidence, 'recipient.name')) ?? $destination->recipient_name ?? 'Not recorded',
                $this->cell(data_get($transferEvidence, 'recipient.office')) ?? $destination?->recipient_office,
                $event->from_location, $event->to_location, $source?->document_no, $destination?->document_no,
                $this->cell(data_get($transferEvidence, 'reason')) ?? $event->notes,
                $transferEvidence ? $this->cell(data_get($transferEvidence, 'transfer_no')).' / '.$this->cell(data_get($transferEvidence, 'approved_by_name')).' / '.$this->cell(data_get($transferEvidence, 'approval_date')) : 'Not recorded',
                $transferEvidence ? $this->cell(data_get($transferEvidence, 'inspection_reference')).' / '.$this->cell(data_get($transferEvidence, 'handover_date')).' / '.$this->cell(data_get($transferEvidence, 'release_reference')).' / '.$this->cell(data_get($transferEvidence, 'receipt_reference')) : 'Not recorded',
                $hasSnapshot ? 'Event snapshot; custody from linked accountability evidence only' : ($propertyDocument !== null ? 'Frozen accountability evidence; incomplete legacy event' : 'Legacy current metadata; custody not inferred'),
            ];
        }

        return [
            'columns' => ['Transfer reference', 'Transfer date', 'Transfer type', 'Date acquired', 'Property No.', 'Description', 'Serial No.', 'Unit acquisition cost', 'Recorded condition', 'From accountable officer', 'From office', 'To accountable officer', 'To office', 'From location', 'To location', 'Previous PAR / ICS', 'Replacement PAR / ICS', 'Recorded remarks', 'Transfer approval', 'Joint inspection / handover', 'Evidence basis'],
            'rows' => $rows,
            'summary' => ['Transfers' => count($rows), 'Evidence limitation' => 'Approval and handover are shown only when linked PTR evidence exists; legacy gaps remain explicit.'],
        ];
    }

    private function document(InventoryAssetEvent $event, string $key): ?PropertyAccountabilityDocument
    {
        $id = $event->details[$key] ?? null;
        if ($id === null) {
            return null;
        }

        return $event->asset?->accountabilityDocuments->first(fn (PropertyAccountabilityDocument $document): bool => (string) $document->getKey() === (string) $id && $this->existedAt($document, $event));
    }

    private function existedAt(PropertyAccountabilityDocument $document, InventoryAssetEvent $event): bool
    {
        return $document->issued_at->toDateString() <= $event->event_date->toDateString()
            && $document->created_at !== null && $event->created_at !== null
            && $document->created_at->lte($event->created_at);
    }

    private function latestDocument(InventoryAssetEvent $event): ?PropertyAccountabilityDocument
    {
        return $event->asset?->accountabilityDocuments
            ->filter(fn (PropertyAccountabilityDocument $document): bool => $this->existedAt($document, $event)
                && ($document->closed_at === null || $document->closed_at->gt($event->event_date->copy()->endOfDay()->min($event->created_at ?? $event->event_date))))
            ->sortByDesc('id')->first();
    }

    private function cell(mixed $value): string|int|float|null
    {
        return is_string($value) || is_int($value) || is_float($value) ? $value : null;
    }
}
