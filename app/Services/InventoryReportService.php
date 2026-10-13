<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryAssetCustodian;
use App\Models\InventoryAssetEvent;
use App\Models\InventoryCountAdjustment;
use App\Models\InventoryCountSession;
use App\Models\InventoryDisposal;
use App\Models\InventoryDisposalLine;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\InventoryReturn;
use App\Models\ProcurementDelivery;
use App\Models\PropertyAccountabilityDocument;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableValueClass;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

class InventoryReportService
{
    public function __construct(
        private PpeDepreciationCalculator $depreciation,
        private InventoryPropertyCardService $propertyCards,
        private InventoryTransferReportService $transfers,
    ) {}

    /** @return array<int, string> */
    public static function documentKeys(): array
    {
        return array_keys(self::documents());
    }

    /** @return array<int, array{key: string, code: string, title: string, group: string, description: string}> */
    public function catalog(): array
    {
        return collect(self::documents())
            ->map(fn (array $document, string $key): array => ['key' => $key, ...$document])
            ->values()
            ->all();
    }

    /**
     * @param  array{as_of: string, period: string, period_label: string, year: int, from: string|null, to: string|null, fund_cluster: string, custodian: int|null}  $filters
     * @return array<string, mixed>
     */
    public function printable(string $document, array $filters): array
    {
        abort_unless(array_key_exists($document, self::documents()), 404);

        $table = match ($document) {
            'rpcppe', 'physical-count-worksheet' => $this->physicalPpe($filters),
            'rpci' => $this->physicalInventories($filters),
            'variance-reconciliation' => $this->varianceReconciliation($filters),
            'par' => $this->assetAccountability($filters, 'PAR'),
            'ics' => $this->assetAccountability($filters, 'ICS'),
            'property-return', 'accountability-employee', 'accountability-office' => $this->assetAccountability($filters),
            'ppe-master-register' => $this->ppeMasterRegister($filters),
            'property-card' => $this->propertyCards($filters),
            'ppe-ledger-card', 'depreciation-schedule' => $this->ppeLedger($filters),
            'capital-additions' => $this->assetEventReport($filters, InventoryAssetEventType::CapitalAddition),
            'maintenance-history' => $this->assetEventReport($filters, InventoryAssetEventType::Maintenance),
            'impairment-register' => $this->assetEventReport($filters, [InventoryAssetEventType::Impairment, InventoryAssetEventType::ImpairmentReversal]),
            'physical-count-history' => $this->assetEventReport($filters, InventoryAssetEventType::PhysicalCount),
            'location-history' => $this->assetEventReport($filters, InventoryAssetEventType::Transfer),
            'fully-depreciated' => $this->fullyDepreciated($filters),
            'missing-property' => $this->missingProperty($filters),
            'ppe-reconciliation' => $this->ppeReconciliation($filters),
            'ppe-audit-trail' => $this->assetEventReport($filters),
            'semi-master-register' => $this->semiExpendableMasterRegister($filters),
            'spc', 'splc' => $this->semiExpendableCard($filters),
            'regspi' => $this->semiExpendableIssuedRegistry($filters),
            'rspi' => $this->semiExpendableIssuanceReport($filters),
            'itr' => $this->transfers->build($filters, AssetAccountingClassification::SemiExpendable),
            'rrsp' => $this->semiExpendableEvents($filters, InventoryAssetEventType::Return),
            'rpcsp' => $this->physicalSemiExpendable($filters),
            'rlsddsp' => $this->semiExpendableLossReport($filters),
            'iirusp' => $this->semiExpendableEvents($filters, InventoryAssetEventType::Unserviceable),
            'ics-renewal' => $this->icsRenewalReport($filters),
            'low-value-expiration' => $this->lowValueExpirationReport($filters),
            'high-value-beyond-life' => $this->highValueBeyondUsefulLife($filters),
            'returned-for-reissue' => $this->returnedForReissue($filters),
            'semi-reconciliation' => $this->semiExpendableReconciliation($filters),
            'semi-audit-trail' => $this->semiExpendableEvents($filters),
            'stock-card', 'supplies-ledger-card' => $this->stockLedger($filters),
            'ris', 'rsmi' => $this->issuedSupplies($filters),
            'iar' => $this->inspectionRegister($filters),
            'receipt-register' => $this->receivedSupplies($filters),
            'delivery-register', 'supplier-return-register' => $this->deliveryRegister($filters, $document === 'supplier-return-register'),
            'return-register', 'return-journal' => $this->returnedSupplies($filters),
            'property-transfer' => $this->propertyTransfers($filters),
            'iirup' => $this->unserviceableProperty($filters),
            'wmr' => $this->wasteMaterials($filters),
            'disposal-register' => $this->disposedProperty($filters),
            'loss-report' => $this->lostProperty($filters),
            'audit-exceptions' => $this->auditExceptions($filters),
        };

        return [
            'document' => ['key' => $document, ...self::documents()[$document]],
            'entity' => config('app.name'),
            'filters' => $filters,
            'columns' => $table['columns'],
            'rows' => $table['rows'],
            'summary' => $table['summary'] ?? null,
            'generatedAt' => now(),
        ];
    }

    /** @return array<string, array{code: string, title: string, group: string, description: string}> */
    private static function documents(): array
    {
        return [
            'rpcppe' => ['code' => 'RPCPPE', 'title' => 'Report on the Physical Count of Property, Plant and Equipment', 'group' => 'Physical audit', 'description' => 'Annual PPE count, condition, shortage and overage.'],
            'rpci' => ['code' => 'RPCI', 'title' => 'Report on the Physical Count of Inventories', 'group' => 'Physical audit', 'description' => 'Consumable balances compared with physical quantities.'],
            'physical-count-worksheet' => ['code' => 'PCW', 'title' => 'Physical Count Worksheet', 'group' => 'Physical audit', 'description' => 'Room-to-room counting sheet for the Inventory Committee.'],
            'variance-reconciliation' => ['code' => 'VRR', 'title' => 'Inventory Variance and Reconciliation Report', 'group' => 'Physical audit', 'description' => 'Book-to-physical differences requiring explanation.'],
            'par' => ['code' => 'PAR', 'title' => 'Property Acknowledgment Receipt', 'group' => 'Accountability', 'description' => 'PPE issued to accountable employees.'],
            'ics' => ['code' => 'ICS', 'title' => 'Inventory Custodian Slip', 'group' => 'Accountability', 'description' => 'Semi-expendable property accountability.'],
            'property-return' => ['code' => 'RRP', 'title' => 'Return and Receipt of Property/Equipment', 'group' => 'Accountability', 'description' => 'Property returned to the Property Unit.'],
            'accountability-employee' => ['code' => 'AAE', 'title' => 'Asset Accountability per Employee', 'group' => 'Accountability', 'description' => 'All property assigned to each employee.'],
            'accountability-office' => ['code' => 'AAO', 'title' => 'Asset Accountability per Office or Location', 'group' => 'Accountability', 'description' => 'Property grouped for location inspection.'],
            'ppe-master-register' => ['code' => 'PPE-MR', 'title' => 'PPE Master Register', 'group' => 'Cards and ledgers', 'description' => 'Complete recognized PPE population and carrying amounts as of a date.'],
            'property-card' => ['code' => 'PC', 'title' => 'Property Card', 'group' => 'Cards and ledgers', 'description' => 'Property-unit PPE record.'],
            'ppe-ledger-card' => ['code' => 'PPELC', 'title' => 'Property, Plant and Equipment Ledger Card', 'group' => 'Cards and ledgers', 'description' => 'Accounting PPE cost and carrying amounts.'],
            'stock-card' => ['code' => 'SC', 'title' => 'Stock Card', 'group' => 'Cards and ledgers', 'description' => 'Receipts, issuances and running stock balance.'],
            'supplies-ledger-card' => ['code' => 'SLC', 'title' => 'Supplies Ledger Card', 'group' => 'Cards and ledgers', 'description' => 'Quantity and monetary inventory ledger.'],
            'depreciation-schedule' => ['code' => 'DS', 'title' => 'PPE Depreciation and Lapsing Schedule', 'group' => 'Cards and ledgers', 'description' => 'Cost, useful life, depreciation and carrying amount.'],
            'capital-additions' => ['code' => 'PPE-ADD', 'title' => 'PPE Capital Additions Register', 'group' => 'Cards and ledgers', 'description' => 'Capitalized subsequent costs with dates and source references.'],
            'maintenance-history' => ['code' => 'PPE-MAINT', 'title' => 'PPE Maintenance History', 'group' => 'Cards and ledgers', 'description' => 'Non-capital maintenance expenditure and work history.'],
            'impairment-register' => ['code' => 'PPE-IMP', 'title' => 'PPE Impairment Register', 'group' => 'Cards and ledgers', 'description' => 'Recognized impairment losses and supporting references.'],
            'physical-count-history' => ['code' => 'PPE-PC', 'title' => 'PPE Physical Count History', 'group' => 'Physical audit', 'description' => 'Dated count results, conditions and variances.'],
            'location-history' => ['code' => 'PPE-LOC', 'title' => 'PPE Location History', 'group' => 'Accountability', 'description' => 'Dated transfers between offices and physical locations.'],
            'fully-depreciated' => ['code' => 'PPE-FD', 'title' => 'Fully Depreciated PPE Still in Service', 'group' => 'Audit controls', 'description' => 'Fully depreciated recognized PPE that remains in active custody or use.'],
            'missing-property' => ['code' => 'PPE-MISS', 'title' => 'Missing PPE and Count Shortage Report', 'group' => 'Audit controls', 'description' => 'Property reported lost or absent from the latest physical count.'],
            'ppe-reconciliation' => ['code' => 'PPE-REC', 'title' => 'PPE Reconciliation Summary', 'group' => 'Audit controls', 'description' => 'Gross cost less accumulated depreciation and impairment, reconciled to carrying amount.'],
            'ppe-audit-trail' => ['code' => 'PPE-AUDIT', 'title' => 'PPE Lifecycle Audit Trail', 'group' => 'Audit controls', 'description' => 'Immutable chronological record of PPE lifecycle, custody and accounting events.'],
            'semi-master-register' => ['code' => 'SE-MR', 'title' => 'Semi-Expendable Property Master Register', 'group' => 'Semi-expendable property', 'description' => 'Current and historical semi-expendable property, without PPE depreciation fields.'],
            'spc' => ['code' => 'SPC', 'title' => 'Semi-Expendable Property Card', 'group' => 'Semi-expendable property', 'description' => 'Transaction-derived property-unit card.'],
            'splc' => ['code' => 'SPLC', 'title' => 'Semi-Expendable Property Ledger Card', 'group' => 'Semi-expendable property', 'description' => 'Accounting-side receipt and issuance ledger.'],
            'regspi' => ['code' => 'RegSPI', 'title' => 'Registry of Semi-Expendable Property Issued', 'group' => 'Semi-expendable property', 'description' => 'Issued, transferred, returned, reissued and disposed property.'],
            'rspi' => ['code' => 'RSPI', 'title' => 'Report of Semi-Expendable Property Issued', 'group' => 'Semi-expendable property', 'description' => 'Period summary sourced from actual ICS issuances.'],
            'itr' => ['code' => 'ITR', 'title' => 'Inventory Transfer Report', 'group' => 'Semi-expendable property', 'description' => 'Custodian and location transfers with retained history.'],
            'rrsp' => ['code' => 'RRSP', 'title' => 'Receipt of Returned Semi-Expendable Property', 'group' => 'Semi-expendable property', 'description' => 'Serviceable and unserviceable returns.'],
            'rpcsp' => ['code' => 'RPCSP', 'title' => 'Report on the Physical Count of Semi-Expendable Property', 'group' => 'Semi-expendable property', 'description' => 'Expected and actual physical counts as of a date.'],
            'rlsddsp' => ['code' => 'RLSDDSP', 'title' => 'Report of Lost, Stolen, Damaged or Destroyed Semi-Expendable Property', 'group' => 'Semi-expendable property', 'description' => 'Incidents, accountable person and relief state.'],
            'iirusp' => ['code' => 'IIRUSP', 'title' => 'Inventory and Inspection Report of Unserviceable Semi-Expendable Property', 'group' => 'Semi-expendable property', 'description' => 'Unserviceable property awaiting authorized disposal.'],
            'ics-renewal' => ['code' => 'ICS-R', 'title' => 'ICS Renewal Report', 'group' => 'Semi-expendable property', 'description' => 'Active, due-soon, overdue, renewed and cancelled ICS records.'],
            'low-value-expiration' => ['code' => 'SE-LVE', 'title' => 'Low-Valued Accountability Expiration Report', 'group' => 'Semi-expendable property', 'description' => 'Due-soon and extinguished low-value accountabilities.'],
            'high-value-beyond-life' => ['code' => 'SE-HV', 'title' => 'High-Valued Items Beyond Estimated Useful Life', 'group' => 'Semi-expendable property', 'description' => 'Expired useful life with accountability intentionally still active.'],
            'returned-for-reissue' => ['code' => 'SE-REISSUE', 'title' => 'Returned Serviceable Property Available for Reissue', 'group' => 'Semi-expendable property', 'description' => 'Returned items available before new procurement.'],
            'semi-reconciliation' => ['code' => 'SE-REC', 'title' => 'Semi-Expendable Property Reconciliation', 'group' => 'Semi-expendable property', 'description' => 'Separates accounting on-hand inventory from physical and active accountability.'],
            'semi-audit-trail' => ['code' => 'SE-AUDIT', 'title' => 'Semi-Expendable Property Audit Trail', 'group' => 'Semi-expendable property', 'description' => 'Immutable lifecycle, ICS, custody, relief and disposal events.'],
            'ris' => ['code' => 'RIS', 'title' => 'Requisition and Issue Slip Register', 'group' => 'Receipts and issues', 'description' => 'Traceable inventory issuances by RIS number.'],
            'rsmi' => ['code' => 'RSMI', 'title' => 'Report of Supplies and Materials Issued', 'group' => 'Receipts and issues', 'description' => 'Period summary of issued supplies.'],
            'iar' => ['code' => 'IAR', 'title' => 'Inspection and Acceptance Report Register', 'group' => 'Receipts and issues', 'description' => 'Accepted deliveries and source references.'],
            'receipt-register' => ['code' => 'RR', 'title' => 'Inventory Receipt Register', 'group' => 'Receipts and issues', 'description' => 'Receipt postings supporting stock and ledger cards.'],
            'return-register' => ['code' => 'RMS-R', 'title' => 'Returned Materials Slip Register', 'group' => 'Receipts and issues', 'description' => 'Approved returns with source issues, inspection, and stock posting references.'],
            'return-journal' => ['code' => 'RMS-J', 'title' => 'Material Returned Journal', 'group' => 'Receipts and issues', 'description' => 'Accepted quantities and costs recognized on approval, separate from supplier receipts.'],
            'delivery-register' => ['code' => 'DEL-R', 'title' => 'Delivery and Inspection Register', 'group' => 'Receipts and issues', 'description' => 'Physical shipment receipt, inspected quantities, acceptance status, and source evidence.'],
            'supplier-return-register' => ['code' => 'RTS-R', 'title' => 'Return to Supplier Register', 'group' => 'Receipts and issues', 'description' => 'Rejected delivery quantities, notification references, and physical supplier handover.'],
            'property-transfer' => ['code' => 'PTR', 'title' => 'Property Transfer Report', 'group' => 'Transfer, loss and disposal', 'description' => 'Custodianship transfers and effective dates.'],
            'iirup' => ['code' => 'IIRUP', 'title' => 'Inventory and Inspection Report of Unserviceable Property', 'group' => 'Transfer, loss and disposal', 'description' => 'Unserviceable property proposed for disposal.'],
            'wmr' => ['code' => 'WMR', 'title' => 'Waste Materials Report', 'group' => 'Transfer, loss and disposal', 'description' => 'Waste and residual property from disposal.'],
            'loss-report' => ['code' => 'RLSDDP', 'title' => 'Report of Lost, Stolen, Damaged or Destroyed Property', 'group' => 'Transfer, loss and disposal', 'description' => 'Property loss and damage incidents.'],
            'disposal-register' => ['code' => 'DR', 'title' => 'Disposal and Derecognition Schedule', 'group' => 'Transfer, loss and disposal', 'description' => 'Disposed items, book values and proceeds.'],
            'audit-exceptions' => ['code' => 'AER', 'title' => 'Inventory Audit Exception Report', 'group' => 'Audit controls', 'description' => 'Missing tags, custodians, references and count differences.'],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function physicalPpe(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get();

        return ['columns' => ['Property No.', 'Description', 'Location', 'Accountable person', 'Unit', 'Card qty.', 'Physical qty.', 'Short/(Over)', 'Unit cost', 'Condition', 'Remarks'], 'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
            $physicalQuantity = $this->physicalQuantityAt($asset, $filters['as_of']);

            return [$asset->property_number, $asset->name, $this->locationAt($asset, $filters['as_of']), $this->custodianAt($asset, $filters['as_of']), $asset->unit_of_measure, $asset->quantity_per_property_card, $physicalQuantity, $asset->quantity_per_property_card - $physicalQuantity, $asset->acquisition_cost, $this->conditionAt($asset, $filters['as_of']), $asset->physical_count_remarks];
        }), 'summary' => ['records' => $assets->count(), 'acquisition cost' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost)]];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function physicalInventories(array $filters): array
    {
        $session = $this->approvedConsumableCount($filters['as_of']);

        $columns = ['Stock No.', 'Article / Description', 'Unit', 'Unit value', 'Balance at count', 'On hand per count', 'Short/(Over)', 'Count value', 'Remarks'];
        if ($session === null) {
            return ['columns' => $columns, 'rows' => collect(), 'summary' => ['count reference' => 'No approved physical count available as of this date']];
        }

        return ['columns' => $columns,
            'rows' => $session->lines->map(fn ($line): array => [
                $line->stock_number, $line->item_name, $line->unit_of_measure, $line->unit_cost,
                $line->book_quantity - $line->before_count_issues, $line->latestEntry?->quantity,
                $line->book_quantity - $line->before_count_issues - $line->latestEntry?->quantity,
                $line->latestEntry->quantity * (float) $line->unit_cost, $line->latestEntry?->remarks,
            ]),
            'summary' => ['count reference' => $session->document_no]];
    }

    private function approvedConsumableCount(string $asOf): ?InventoryCountSession
    {
        $endOfDay = Carbon::parse($asOf, 'Asia/Manila')->endOfDay()->timezone(config('app.timezone'));

        return InventoryCountSession::query()->where('status', 'approved')
            ->where('cutoff_at', '<=', $endOfDay)->where('completed_at', '<=', $endOfDay)
            ->orderByDesc('cutoff_at')->orderByDesc('id')->with(['lines.latestEntry', 'lines.adjustments'])->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function varianceReconciliation(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->get()->filter(fn (InventoryAsset $asset): bool => $asset->quantity_per_property_card !== $this->physicalQuantityAt($asset, $filters['as_of']));

        $rows = $assets->map(function (InventoryAsset $asset) use ($filters): array {
            $physicalQuantity = $this->physicalQuantityAt($asset, $filters['as_of']);
            $variance = $physicalQuantity - $asset->quantity_per_property_card;

            return ['PPE', $asset->property_number, $asset->name, $asset->quantity_per_property_card, $physicalQuantity, $variance, $asset->acquisition_cost, $variance * (float) $asset->acquisition_cost, $asset->physical_count_remarks];
        })->all();
        $count = $this->approvedConsumableCount($filters['as_of']);
        foreach ($count === null ? [] : $count->lines as $line) {
            $expected = $line->book_quantity - $line->before_count_issues;
            $variance = $line->latestEntry->quantity - $expected;
            if ($variance !== 0) {
                $rows[] = ['Consumable', $line->tag_no, $line->item_name, $expected, $line->latestEntry->quantity, $variance,
                    $line->unit_cost, $line->adjustments->sum(fn ($adjustment): float => $adjustment->quantity * (float) $adjustment->unit_cost), $line->latestEntry->remarks];
            }
        }

        return ['columns' => ['Type', 'Reference', 'Description', 'Book/Card balance', 'Physical count', 'Variance', 'Unit value', 'Variance value', 'Explanation / action'], 'rows' => collect($rows)];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function assetAccountability(array $filters, ?string $documentType = null): array
    {
        $assets = $this->assetsForPrint($filters)
            ->whereNotNull('current_custodian_reference_id')
            ->when(
                $documentType === 'PAR',
                fn (Builder $query) => $query->where('accounting_classification', AssetAccountingClassification::Ppe),
            )
            ->when(
                $documentType === 'ICS',
                fn (Builder $query) => $query->where('accounting_classification', AssetAccountingClassification::SemiExpendable),
            )
            ->get();

        return ['columns' => ['Accountable person', 'Employee No.', 'Property No.', 'Description', 'Serial No.', 'Unit', 'Qty.', 'Acquisition date', 'Amount', 'Location', 'Condition'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [
            $asset->currentCustodian?->name, $asset->currentCustodian?->code, $asset->property_number, $asset->name, $asset->serial_number,
            $asset->unit_of_measure, $asset->quantity_per_property_card, $asset->acquisition_date?->toDateString(), $asset->acquisition_cost, $asset->location, $asset->condition_status->label(),
        ]), 'summary' => ['records' => $assets->count(), 'amount' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost)]];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function ppeMasterRegister(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get();
        $rows = $assets->map(function (InventoryAsset $asset) use ($filters): array {
            $valuation = $this->depreciation->calculate($asset, $filters['as_of']);

            return [
                $asset->property_number,
                $asset->old_property_number,
                $asset->name,
                $asset->category?->name,
                $asset->account_code,
                $asset->fund_cluster,
                $asset->serial_number,
                $this->locationAt($asset, $filters['as_of']),
                $this->custodianAt($asset, $filters['as_of']),
                $asset->acquisition_date?->toDateString(),
                $asset->available_for_use_date?->toDateString(),
                $valuation->grossCarryingAmount,
                $valuation->accumulatedDepreciation,
                $valuation->impairmentLosses,
                $valuation->carryingAmount,
                $this->conditionAt($asset, $filters['as_of']),
            ];
        });

        return [
            'columns' => ['Property No.', 'Old property No.', 'Description', 'PPE class', 'Account code', 'Fund cluster', 'Serial No.', 'Location', 'Accountable person', 'Acquired', 'Available for use', 'Gross amount', 'Accumulated depreciation', 'Impairment', 'Carrying amount', 'Condition'],
            'rows' => $rows,
            'summary' => $this->valuationSummary($assets, $filters['as_of']),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    /** @param InventoryAssetEventType|list<InventoryAssetEventType>|null $types */
    private function assetEventReport(array $filters, InventoryAssetEventType|array|null $types = null): array
    {
        $events = InventoryAssetEvent::query()
            ->with(['asset.category', 'recordedBy:id,name'])
            ->when($types, function (Builder $query, InventoryAssetEventType|array $eventTypes): void {
                $query->whereIn('event_type', array_map(
                    fn (InventoryAssetEventType $eventType): string => $eventType->value,
                    is_array($eventTypes) ? $eventTypes : [$eventTypes],
                ));
            })
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when(
                $filters['from'] && $filters['to'],
                fn (Builder $query) => $query->whereBetween('event_date', [$filters['from'], $filters['to']]),
            )
            ->when(
                $filters['fund_cluster'] !== '',
                fn (Builder $query) => $query->whereHas('asset', fn (Builder $assetQuery) => $assetQuery->where('fund_cluster', $filters['fund_cluster'])),
            )
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();
        $totalAmount = $events->reduce(
            fn (BigDecimal $total, InventoryAssetEvent $event): BigDecimal => $total->plus((string) ($event->amount ?? 0)),
            BigDecimal::zero(),
        );

        return [
            'columns' => ['Date', 'Event', 'Reference', 'Property No.', 'Description', 'PPE class', 'Amount', 'Quantity', 'From location', 'To location', 'Condition', 'Details / remarks', 'Recorded by'],
            'rows' => $events->map(fn (InventoryAssetEvent $event): array => [
                $event->event_date->toDateString(),
                $event->event_type->label(),
                $event->reference_no,
                $event->asset?->property_number,
                $event->asset?->name,
                $event->asset?->category?->name,
                $event->amount,
                $event->quantity,
                $event->from_location,
                $event->to_location,
                $event->condition_status?->label(),
                collect([$event->notes, $this->eventDetails($event)])->filter()->join(' | '),
                $event->recordedBy?->name,
            ]),
            'summary' => [
                'records' => $events->count(),
                'amount' => (string) $totalAmount->toScale(2, RoundingMode::HalfUp),
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function fullyDepreciated(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get()
            ->filter(fn (InventoryAsset $asset): bool => $this->depreciation
                ->calculate($asset, $filters['as_of'])
                ->isFullyDepreciated);

        return [
            'columns' => ['Property No.', 'Description', 'PPE class', 'Accountable person', 'Location', 'Available for use', 'Gross amount', 'Accumulated depreciation', 'Carrying amount', 'Condition'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $valuation = $this->depreciation->calculate($asset, $filters['as_of']);

                return [$asset->property_number, $asset->name, $asset->category?->name, $this->custodianAt($asset, $filters['as_of']), $this->locationAt($asset, $filters['as_of']), $asset->available_for_use_date?->toDateString(), $valuation->grossCarryingAmount, $valuation->accumulatedDepreciation, $valuation->carryingAmount, $asset->condition_status->label()];
            }),
            'summary' => ['records' => $assets->count()],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function missingProperty(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get()
            ->filter(function (InventoryAsset $asset) use ($filters): bool {
                $lost = $asset->events->contains(fn (InventoryAssetEvent $event): bool => $event->event_type === InventoryAssetEventType::Loss
                    && $event->event_date->lte($filters['as_of']));
                $latestCount = $asset->events
                    ->filter(fn (InventoryAssetEvent $event): bool => $event->event_type === InventoryAssetEventType::PhysicalCount
                        && $event->event_date->lte($filters['as_of']))
                    ->sortByDesc('event_date')
                    ->first();

                return $lost || ($latestCount !== null && $latestCount->quantity < $asset->quantity_per_property_card);
            });

        return [
            'columns' => ['Property No.', 'Description', 'Accountable person', 'Location', 'Card quantity', 'Latest count', 'Variance', 'Latest report / reference', 'Remarks'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $latestEvent = $asset->events
                    ->filter(fn (InventoryAssetEvent $event): bool => in_array($event->event_type, [InventoryAssetEventType::PhysicalCount, InventoryAssetEventType::Loss], true)
                        && $event->event_date->lte($filters['as_of']))
                    ->sortByDesc('event_date')
                    ->first();
                $count = $latestEvent?->event_type === InventoryAssetEventType::PhysicalCount
                    ? $latestEvent->quantity
                    : 0;

                return [$asset->property_number, $asset->name, $this->custodianAt($asset, $filters['as_of']), $this->locationAt($asset, $filters['as_of']), $asset->quantity_per_property_card, $count, $count - $asset->quantity_per_property_card, $latestEvent?->reference_no, $latestEvent?->notes];
            }),
            'summary' => ['records' => $assets->count()],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function ppeReconciliation(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get();

        return [
            'columns' => ['Property No.', 'Description', 'PPE class', 'Fund cluster', 'Gross amount', 'Less: accumulated depreciation', 'Less: impairment', 'Carrying amount', 'Equation difference'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $valuation = $this->depreciation->calculate($asset, $filters['as_of']);
                $difference = BigDecimal::of($valuation->grossCarryingAmount)
                    ->minus($valuation->accumulatedDepreciation)
                    ->minus($valuation->impairmentLosses)
                    ->minus($valuation->carryingAmount);

                return [$asset->property_number, $asset->name, $asset->category?->name, $asset->fund_cluster, $valuation->grossCarryingAmount, $valuation->accumulatedDepreciation, $valuation->impairmentLosses, $valuation->carryingAmount, (string) $difference->toScale(2, RoundingMode::HalfUp)];
            }),
            'summary' => $this->valuationSummary($assets, $filters['as_of']),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function propertyCards(array $filters): array
    {
        return $this->propertyCards->build($filters, AssetAccountingClassification::Ppe);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function ppeLedger(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::Ppe)
            ->get();

        return [
            'columns' => ['Property No.', 'PPE class', 'Description', 'Available for use', 'Gross amount', 'Useful life (months)', 'Residual value', 'Monthly depreciation', 'Accumulated depreciation', 'Impairment', 'Carrying amount'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $valuation = $this->depreciation->calculate($asset, $filters['as_of']);

                return [$asset->property_number, $asset->category?->name, $asset->name, $asset->available_for_use_date?->toDateString(), $valuation->grossCarryingAmount, $asset->depreciation_useful_life_months, $valuation->residualValue, $valuation->monthlyDepreciation, $valuation->accumulatedDepreciation, $valuation->impairmentLosses, $valuation->carryingAmount];
            }),
            'summary' => $this->valuationSummary($assets, $filters['as_of']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function stockLedger(array $filters): array
    {
        $rows = collect();
        $shortages = InventoryCountAdjustment::query()->where('quantity', '<', 0)->with('line.session')->get()->groupBy('line.inventory_item_id');
        InventoryItem::withTrashed()->with(['batches.countAdjustments', 'batches.returnPostings', 'stockOuts.allocations', 'stockOuts.issuanceSlip'])->orderBy('stock_number')->each(function (InventoryItem $item) use ($rows, $filters, $shortages): void {
            $transactions = collect();
            foreach ($item->batches as $batch) {
                $kind = $batch->returnPostings->isNotEmpty() ? 'Return to stock' : ($batch->countAdjustments->where('quantity', '>', 0)->isNotEmpty() ? 'Adjustment (overage)' : 'Receipt');
                $transactions->push([$item->stock_number, $item->name, $batch->received_at->toDateString(), $batch->reference_no, $kind, $batch->quantity_in, null, $batch->unit_cost,
                    (string) BigDecimal::of($batch->unit_cost)->multipliedBy($batch->quantity_in), $batch->source, $batch->notes, $item->unit_of_measure, null,
                    $batch->created_at->format('Y-m-d H:i:s').'|0|'.sprintf('%020d', $batch->getKey())]);
            }
            foreach ($item->stockOuts as $issue) {
                $amount = BigDecimal::of('0.00');
                foreach ($issue->allocations as $allocation) {
                    $amount = $amount->plus(BigDecimal::of($allocation->unit_cost)->multipliedBy($allocation->quantity));
                }
                $transactions->push([$item->stock_number, $item->name, $issue->stocked_out_at->toDateString(), $issue->issuanceSlip->slip_no ?? $issue->ris_no, 'Issue', null, $issue->quantity,
                    $issue->quantity > 0 ? (string) $amount->dividedBy($issue->quantity, 6, RoundingMode::HalfUp) : '0.000000',
                    (string) $amount, $issue->recipient_name, $issue->notes, $item->unit_of_measure, $issue->ris_no,
                    $issue->created_at->format('Y-m-d H:i:s').'|1|'.sprintf('%020d', $issue->getKey())]);
            }
            $adjustments = $shortages->get($item->getKey(), collect());
            foreach ($adjustments as $adjustment) {
                $transactions->push([$item->stock_number, $item->name, $adjustment->line->session->completed_at->timezone('Asia/Manila')->toDateString(), $adjustment->line->session->document_no,
                    'Adjustment (shortage)', null, -$adjustment->quantity, $adjustment->unit_cost,
                    (string) BigDecimal::of($adjustment->unit_cost)->multipliedBy(-$adjustment->quantity), null, 'Approved count adjustment', $item->unit_of_measure, null,
                    $adjustment->created_at->format('Y-m-d H:i:s').'|2|'.sprintf('%020d', $adjustment->getKey())]);
            }
            $balance = 0;
            foreach ($transactions->sortBy(fn (array $row): string => $row[2].'|'.$row[13])->values() as $row) {
                $balance += ($row[5] ?? 0) - ($row[6] ?? 0);
                if ($row[2] <= $filters['as_of'] && (! $filters['from'] || $row[2] >= $filters['from']) && (! $filters['to'] || $row[2] <= $filters['to'])) {
                    $rows->push([...array_slice($row, 0, 9), $balance, ...array_slice($row, 9, 4)]);
                }
            }
        });

        return ['columns' => ['Stock No.', 'Article', 'Date', 'Reference', 'Transaction', 'Received', 'Issued / adjusted out', 'Unit cost / blended issue cost', 'Amount', 'Running balance', 'Supplier / source / recipient', 'Purpose / remarks', 'Unit', 'RIS / request reference'], 'rows' => $rows];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function issuedSupplies(array $filters): array
    {
        $issues = InventoryItemStockOut::query()
            ->with(['item', 'recipientReference', 'allocations'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('stocked_out_at', [$filters['from'], $filters['to']]))
            ->orderBy('stocked_out_at')
            ->get();

        return ['columns' => ['Date', 'RIS No.', 'Responsibility center', 'Stock No.', 'Article', 'Unit', 'Qty.', 'Unit cost', 'Amount', 'Recipient'], 'rows' => $issues->map(fn (InventoryItemStockOut $issue): array => [$issue->stocked_out_at->toDateString(), $issue->ris_no, $issue->responsibility_center_code, $issue->item?->stock_number, $issue->item?->name, $issue->item?->unit_of_measure, $issue->quantity, $issue->quantity > 0 ? (float) $issue->total_cost / $issue->quantity : 0, $issue->total_cost, $issue->recipientReference?->name ?? $issue->recipient_name]), 'summary' => ['quantity issued' => $issues->sum('quantity'), 'amount' => $issues->sum(fn (InventoryItemStockOut $issue): float => (float) $issue->total_cost)]];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function receivedSupplies(array $filters): array
    {
        $batches = InventoryItemBatch::query()
            ->with(['item' => fn ($query) => $query->withTrashed()])
            ->whereDoesntHave('countAdjustments', fn ($query) => $query->where('quantity', '>', 0))
            ->whereDoesntHave('returnPostings')
            ->whereDate('received_at', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereDate('received_at', '>=', $filters['from'])->whereDate('received_at', '<=', $filters['to']))
            ->orderBy('received_at')
            ->get();

        return ['columns' => ['Date received', 'Reference / IAR', 'Source / Supplier', 'Stock No.', 'Description', 'Unit', 'Qty. accepted', 'Unit cost', 'Amount', 'Inspection remarks'], 'rows' => $batches->map(fn (InventoryItemBatch $batch): array => [$batch->received_at->toDateString(), $batch->reference_no, $batch->source, $batch->item?->stock_number, $batch->item?->name, $batch->item?->unit_of_measure, $batch->quantity_in, $batch->unit_cost, $batch->quantity_in * (float) $batch->unit_cost, $batch->notes]), 'summary' => ['quantity received' => $batches->sum('quantity_in'), 'amount' => $batches->sum(fn (InventoryItemBatch $batch): float => $batch->quantity_in * (float) $batch->unit_cost)]];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function inspectionRegister(array $filters): array
    {
        $legacy = InventoryItemBatch::query()->with(['item' => fn ($query) => $query->withTrashed()])->whereDoesntHave('deliveryLine')
            ->whereDoesntHave('returnPostings')->whereDoesntHave('countAdjustments', fn ($query) => $query->where('quantity', '>', 0))
            ->whereDate('received_at', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn ($query) => $query->whereDate('received_at', '>=', $filters['from'])->whereDate('received_at', '<=', $filters['to']))->orderBy('received_at')->get();
        $rows = $legacy->map(fn (InventoryItemBatch $batch): array => [
            $batch->received_at->toDateString(), $batch->reference_no, $batch->source, $batch->item?->stock_number,
            $batch->item?->name, $batch->item?->unit_of_measure, $batch->quantity_in, $batch->unit_cost,
            (string) BigDecimal::of($batch->unit_cost)->multipliedBy($batch->quantity_in), $batch->notes,
        ]);
        $documents = ProcurementDelivery::query()->where('status', 'accepted')->with(['lines.orderLine.item' => fn ($query) => $query->withTrashed()])
            ->where('accepted_at', '<=', Carbon::parse($filters['as_of'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')))
            ->when($filters['from'] && $filters['to'], fn ($query) => $query->whereBetween('accepted_at', [
                Carbon::parse($filters['from'], 'Asia/Manila')->startOfDay()->timezone(config('app.timezone')),
                Carbon::parse($filters['to'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')),
            ]))->orderBy('accepted_at')->get();
        foreach ($documents as $delivery) {
            foreach ($delivery->lines as $line) {
                if (($line->quantity_accepted ?? 0) === 0) {
                    continue;
                }
                $rows->push([$delivery->accepted_at->timezone('Asia/Manila')->toDateString(), $delivery->inspection_acceptance_no,
                    $delivery->supplier_name, $line->orderLine->item->stock_number ?? 'PR-L'.$line->procurement_request_line_id,
                    $line->item_name, $line->unit_of_measure, $line->quantity_accepted, $line->unit_cost,
                    (string) BigDecimal::of($line->unit_cost)->multipliedBy($line->quantity_accepted),
                    $delivery->document_no.' / Invoice '.$delivery->invoice_number.' / DR '.$delivery->delivery_reference.' / '.$line->findings]);
            }
        }

        return ['columns' => ['Acceptance / posting date', 'IAR / source reference', 'Supplier', 'Stock / order line', 'Description', 'Unit',
            'Qty. accepted', 'Unit cost', 'Amount', 'Inspection and delivery evidence'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function deliveryRegister(array $filters, bool $supplierReturns): array
    {
        $documents = ProcurementDelivery::query()->with('lines')
            ->when($supplierReturns, fn ($query) => $query->whereNotNull('supplier_return_no')->whereIn('status', ['inspected', 'accepted', 'rejected']))
            ->where('received_at', '<=', Carbon::parse($filters['as_of'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')))
            ->when($filters['from'] && $filters['to'], fn ($query) => $query->whereBetween('received_at', [
                Carbon::parse($filters['from'], 'Asia/Manila')->startOfDay()->timezone(config('app.timezone')),
                Carbon::parse($filters['to'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')),
            ]))->orderBy('received_at')->get();
        $rows = collect();
        foreach ($documents as $delivery) {
            foreach ($delivery->lines as $line) {
                $rejected = $line->quantity_accepted === null ? null : $line->quantity_delivered - $line->quantity_accepted;
                if ($supplierReturns && ($rejected ?? 0) === 0) {
                    continue;
                }
                $rows->push([$delivery->document_no, $delivery->purchase_order_no, $delivery->supplier_name, $delivery->invoice_number,
                    $delivery->delivery_reference, $delivery->received_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                    $delivery->status, $line->item_name, $line->unit_of_measure, $line->quantity_delivered, $line->quantity_accepted,
                    $rejected, $delivery->inspection_acceptance_no, $delivery->supplier_return_no,
                    $delivery->supplier_returned_at?->timezone('Asia/Manila')->toDateString(),
                    $line->findings, $delivery->inspection['supplier_notification_reference'] ?? null]);
            }
        }

        return ['columns' => ['Shipment', 'PO', 'Supplier', 'Invoice', 'Delivery reference', 'Physical receipt', 'Status', 'Material', 'Unit',
            'Delivered', 'Inspected accepted', 'Rejected', 'IAR', 'Supplier notice', 'Returned to supplier', 'Inspection findings', 'Discrepancy notification'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function returnedSupplies(array $filters): array
    {
        $documents = InventoryReturn::query()->where('status', 'approved')->with('lines.posting')
            ->where('approved_at', '<=', Carbon::parse($filters['as_of'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')))
            ->when($filters['from'] && $filters['to'], fn ($query) => $query->whereBetween('approved_at', [
                Carbon::parse($filters['from'], 'Asia/Manila')->startOfDay()->timezone(config('app.timezone')),
                Carbon::parse($filters['to'], 'Asia/Manila')->endOfDay()->timezone(config('app.timezone')),
            ]))->orderBy('approved_at')->get();
        $rows = collect();
        foreach ($documents as $document) {
            foreach ($document->lines as $line) {
                if ($line->posting !== null) {
                    $rows->push([$document->document_no, $document->return_date->toDateString(), $document->approved_at->timezone('Asia/Manila')->toDateString(),
                        $document->returned_by_name, $document->department_name, $line->source_issue_reference, $line->stock_number,
                        $line->item_name, $line->unit_of_measure, $line->quantity_declared, $line->quantity_accepted, $line->unit_cost,
                        (string) BigDecimal::of($line->unit_cost)->multipliedBy($line->quantity_accepted), $line->storage_location, $line->inspection_remarks]);
                }
            }
        }

        return ['columns' => ['RMS No.', 'Physical return date', 'Posted date', 'Returned by', 'Department', 'Source issue', 'Stock No.',
            'Description', 'Unit', 'Declared', 'Accepted', 'Unit cost', 'Amount', 'Storage location', 'Inspection findings'], 'rows' => $rows];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function propertyTransfers(array $filters): array
    {
        return $this->transfers->build($filters);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function unserviceableProperty(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->where(fn (Builder $query) => $query->where('condition_status', AssetConditionStatus::NonUsable->value)->orWhere('lifecycle_status', AssetLifecycleStatus::Retired->value))->get();

        return ['columns' => ['Property No.', 'Description', 'Acquired', 'Gross amount', 'Accumulated depreciation', 'Carrying amount', 'Condition', 'Location', 'Appraised value', 'Disposal recommendation'], 'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
            $valuation = $this->depreciation->calculate($asset, $filters['as_of']);

            return [$asset->property_number, $asset->name, $asset->acquisition_date?->toDateString(), $valuation->grossCarryingAmount, $valuation->accumulatedDepreciation, $valuation->carryingAmount, $asset->condition_status->label(), $this->locationAt($asset, $filters['as_of']), $asset->appraised_value, $asset->disposal_method];
        })];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function wasteMaterials(array $filters): array
    {
        $documents = InventoryDisposal::query()->with(['lines', 'actions'])
            ->where('status', 'completed')->whereDate('completed_at', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereDate('completed_at', '>=', $filters['from'])->whereDate('completed_at', '<=', $filters['to']))
            ->when($filters['custodian'], fn (Builder $query) => $query->where('certifier_snapshot->id', $filters['custodian']))
            ->orderBy('completed_at')->orderBy('id')->get();

        return ['columns' => ['WMR', 'Posted date', 'Physical disposition date', 'Storage', 'Description / source', 'Quantity', 'Unit', 'Method', 'Official receipt', 'Proceeds', 'Certified by', 'Approved by', 'Inspector', 'Internal Control witness', 'Accounting witness', 'Accounting reference'],
            'rows' => $documents->flatMap(function (InventoryDisposal $document) {
                $approval = $document->actions->firstWhere('action', 'approve');
                $completion = $document->actions->firstWhere('action', 'complete');

                return $document->lines->map(fn (InventoryDisposalLine $line): array => [
                    $document->document_no, $document->completed_at?->toDateString(), data_get($completion?->evidence, 'disposition_date'),
                    $document->place_of_storage, $line->description.' / '.$line->source_reference, $line->quantity, $line->unit,
                    $document->method, $line->or_number, $line->proceeds, $document->certifier_snapshot['name'],
                    data_get($approval?->evidence, 'chair.name'), data_get($completion?->evidence, 'inspector.name'),
                    data_get($completion?->evidence, 'internal_control_witness.name'), data_get($completion?->evidence, 'accounting_witness.name'),
                    data_get($completion?->evidence, 'accounting_reference'),
                ])->all();
            })->all(),
            'summary' => ['Scope' => 'Completed WMRs, including standalone components. Dates filter ledger posting; fund cluster is a reporting label. Legacy asset disposals remain in the Disposal Register.'],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function disposedProperty(array $filters): array
    {
        $events = InventoryAssetEvent::query()
            ->with(['asset.category', 'asset.events'])
            ->where('event_type', InventoryAssetEventType::Disposal)
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('event_date', [$filters['from'], $filters['to']]))
            ->orderBy('event_date')
            ->get();

        return ['columns' => ['Date', 'Approval / reference', 'Property No.', 'Description / waste material', 'Gross amount', 'Accumulated depreciation', 'Carrying amount at disposal', 'Method', 'Appraised value', 'Proceeds', 'Gain/(Loss)', 'Recipient / remarks'], 'rows' => $events->map(function (InventoryAssetEvent $event): array {
            $asset = $event->asset;
            $valuation = $this->depreciation->calculate($asset, $event->event_date->subDay());
            $proceeds = (string) data_get($event->details, 'proceeds', $event->amount ?? 0);

            $gainOrLoss = BigDecimal::of($proceeds)->minus($valuation->carryingAmount);

            return [$event->event_date->toDateString(), data_get($event->details, 'approval_no', $event->reference_no), $asset?->property_number, $asset?->name, $valuation->grossCarryingAmount, $valuation->accumulatedDepreciation, $valuation->carryingAmount, data_get($event->details, 'disposal_method'), data_get($event->details, 'appraised_value'), $proceeds, (string) $gainOrLoss->toScale(2, RoundingMode::HalfUp), data_get($event->details, 'recipient') ?? $event->notes];
        })];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function lostProperty(array $filters): array
    {
        $events = InventoryAssetEvent::query()
            ->with(['asset.events', 'asset.currentCustodian'])
            ->where('event_type', InventoryAssetEventType::Loss)
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('event_date', [$filters['from'], $filters['to']]))
            ->orderBy('event_date')
            ->get();

        return ['columns' => ['Report No.', 'Report date', 'Type', 'Property No.', 'Description', 'Accountable person', 'Gross amount', 'Carrying amount', 'Circumstances'], 'rows' => $events->map(function (InventoryAssetEvent $event): array {
            $valuation = $this->depreciation->calculate($event->asset, $event->event_date);

            return [$event->reference_no, $event->event_date->toDateString(), data_get($event->details, 'loss_type'), $event->asset?->property_number, $event->asset?->name, $event->asset?->currentCustodian?->name, $valuation->grossCarryingAmount, $valuation->carryingAmount, data_get($event->details, 'circumstances', $event->notes)];
        })];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function auditExceptions(array $filters): array
    {
        $rows = collect();
        $this->assetsForPrint($filters)->get()->each(function (InventoryAsset $asset) use ($rows): void {
            $recordType = $asset->accounting_classification === AssetAccountingClassification::Ppe
                ? 'PPE'
                : 'Semi-expendable';
            if ($asset->property_number === null) {
                $rows->push([$recordType, $asset->serial_number, $asset->name, 'Missing property number', 'Assign and tag before count']);
            }
            if ($asset->current_custodian_reference_id === null && $asset->lifecycle_status === AssetLifecycleStatus::Active) {
                $rows->push([$recordType, $asset->property_number, $asset->name, 'No accountable custodian', 'Issue PAR/ICS or document storage custody']);
            }
            if ($asset->quantity_per_property_card !== $asset->quantity_per_physical_count) {
                $rows->push([$recordType, $asset->property_number, $asset->name, 'Physical count variance', 'Investigate and reconcile']);
            }
            if ($asset->accounting_classification === AssetAccountingClassification::SemiExpendable) {
                $cost = BigDecimal::of((string) $asset->acquisition_cost);
                $expectedClass = $cost->isLessThanOrEqualTo((string) config('inventory.semi_expendable.low_value_threshold'))
                    ? SemiExpendableValueClass::Low
                    : SemiExpendableValueClass::High;

                if ($cost->isGreaterThanOrEqualTo(AssetAccountingClassification::capitalizationThreshold())) {
                    $rows->push([$recordType, $asset->property_number, $asset->name, 'Cost meets the PPE threshold', 'Review cross-module classification before reclassification']);
                }
                if ($asset->semi_expendable_value_class !== $expectedClass) {
                    $rows->push([$recordType, $asset->property_number, $asset->name, 'Low/high value class conflicts with unit cost', 'Run an authorized classification correction']);
                }
                if ($asset->current_custodian_reference_id !== null
                    && ! $asset->accountabilityDocuments->where('document_type', 'ICS')->whereIn('status', ['pending_recipient', 'active'])->count()) {
                    $rows->push([$recordType, $asset->property_number, $asset->name, 'Assigned property has no active ICS', 'Issue or reconcile the missing ICS']);
                }
                if ($asset->physical_status === SemiExpendablePhysicalStatus::Disposed
                    && in_array($asset->accountability_status, [SemiExpendableAccountabilityStatus::PendingRecipient, SemiExpendableAccountabilityStatus::Accountable, SemiExpendableAccountabilityStatus::PendingRelief], true)) {
                    $rows->push([$recordType, $asset->property_number, $asset->name, 'Disposed property retains active accountability', 'Review disposal and accountability closure history']);
                }
            }
        });
        InventoryItemStockOut::query()->with('item')->whereNull('ris_no')->each(fn (InventoryItemStockOut $issue) => $rows->push(['Inventory', $issue->getKey(), $issue->item?->name, 'Issue without RIS number', 'Complete source document reference']));
        InventoryItemBatch::query()->with('item')->whereNull('reference_no')->each(fn (InventoryItemBatch $batch) => $rows->push(['Inventory', $batch->getKey(), $batch->item?->name, 'Receipt without IAR/reference number', 'Complete receipt source document']));

        return ['columns' => ['Record type', 'Reference', 'Description', 'Exception', 'Required action'], 'rows' => $rows, 'summary' => ['exceptions' => $rows->count()]];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableMasterRegister(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->with('accountabilityDocuments')
            ->get();

        return [
            'columns' => ['Property No.', 'Description', 'Category', 'Serial No.', 'Unit cost', 'Value class', 'Acquired', 'Fund cluster', 'Accounting state', 'Physical state', 'Condition', 'Accountable person', 'Location', 'Active ICS', 'Renewal due', 'Accountability expiration', 'Accountability state'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $document = $asset->accountabilityDocuments
                    ->whereIn('status', ['pending_recipient', 'active', 'expired'])
                    ->sortByDesc('issued_at')
                    ->first();

                return [
                    $asset->property_number, $asset->name, $asset->category?->name, $asset->serial_number,
                    $asset->acquisition_cost, $asset->semi_expendable_value_class?->label(), $asset->acquisition_date?->toDateString(),
                    $asset->fund_cluster, $asset->accounting_status->label(), $asset->physical_status?->label(),
                    $this->conditionAt($asset, $filters['as_of']), $this->custodianAt($asset, $filters['as_of']),
                    $this->locationAt($asset, $filters['as_of']), $document?->document_no,
                    $document?->renewal_due_at?->toDateString(), $document?->accountability_expires_at?->toDateString(),
                    $asset->accountability_status?->label(),
                ];
            }),
            'summary' => [
                'records' => $assets->count(),
                'acquisition cost' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost),
                'accounting on hand' => $assets->filter(fn (InventoryAsset $asset): bool => $asset->accounting_status === AssetAccountingStatus::OnHand)->count(),
                'active accountability' => $assets->filter(fn (InventoryAsset $asset): bool => in_array($asset->accountability_status, [
                    SemiExpendableAccountabilityStatus::PendingRecipient,
                    SemiExpendableAccountabilityStatus::Accountable,
                    SemiExpendableAccountabilityStatus::PendingRelief,
                ], true))->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableCard(array $filters): array
    {
        return $this->propertyCards->build($filters, AssetAccountingClassification::SemiExpendable);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableIssuedRegistry(array $filters): array
    {
        return $this->semiExpendableEvents($filters, [
            InventoryAssetEventType::Issuance,
            InventoryAssetEventType::IcsCreated,
            InventoryAssetEventType::IcsRenewed,
            InventoryAssetEventType::Assignment,
            InventoryAssetEventType::Transfer,
            InventoryAssetEventType::Return,
            InventoryAssetEventType::Reissue,
            InventoryAssetEventType::AccountabilityExpired,
            InventoryAssetEventType::AccountabilityExtinguished,
            InventoryAssetEventType::Disposal,
        ]);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableIssuanceReport(array $filters): array
    {
        $documents = PropertyAccountabilityDocument::query()
            ->with(['asset.category'])
            ->where('document_type', 'ICS')
            ->whereDate('issued_at', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('issued_at', [$filters['from'], $filters['to']]))
            ->when($filters['fund_cluster'] !== '', fn (Builder $query) => $query->where('fund_cluster', $filters['fund_cluster']))
            ->orderBy('issued_at')->get();

        return [
            'columns' => ['ICS No.', 'Date', 'Fund cluster', 'Property class', 'Property No.', 'Description', 'Qty.', 'Unit cost', 'Amount', 'Value class', 'Accountable person', 'Status'],
            'rows' => $documents->map(fn (PropertyAccountabilityDocument $document): array => [
                $document->document_no, $document->issued_at->toDateString(), $document->fund_cluster,
                $document->asset?->category?->name, $document->property_number, $document->asset_name,
                $document->quantity, $document->acquisition_cost, (float) $document->acquisition_cost * $document->quantity,
                str($document->value_class)->title(), $document->recipient_name, str($document->status)->replace('_', ' ')->title(),
            ]),
            'summary' => ['ICS records' => $documents->count(), 'amount' => $documents->sum(fn (PropertyAccountabilityDocument $document): float => (float) $document->acquisition_cost * $document->quantity)],
        ];
    }

    /** @param array<string, mixed> $filters @param InventoryAssetEventType|list<InventoryAssetEventType>|null $types @return array<string, mixed> */
    private function semiExpendableEvents(array $filters, InventoryAssetEventType|array|null $types = null): array
    {
        $events = InventoryAssetEvent::query()
            ->with(['asset.category', 'asset.currentCustodian:id,name,code', 'recordedBy:id,name'])
            ->whereHas('asset', fn (Builder $query) => $query->where('accounting_classification', AssetAccountingClassification::SemiExpendable))
            ->when($types, fn (Builder $query, InventoryAssetEventType|array $selected) => $query->whereIn('event_type', array_map(
                fn (InventoryAssetEventType $type): string => $type->value,
                is_array($selected) ? $selected : [$selected],
            )))
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('event_date', [$filters['from'], $filters['to']]))
            ->when($filters['fund_cluster'] !== '', fn (Builder $query) => $query->whereHas('asset', fn (Builder $assetQuery) => $assetQuery->where('fund_cluster', $filters['fund_cluster'])))
            ->orderBy('event_date')->orderBy('id')->get();

        return [
            'columns' => ['Date', 'Transaction', 'Reference', 'Property No.', 'Description', 'Value class', 'Accountable person', 'From location', 'To location', 'Condition', 'Amount', 'Remarks', 'Recorded by'],
            'rows' => $events->map(fn (InventoryAssetEvent $event): array => [
                $event->event_date->toDateString(), $event->event_type->label(), $event->reference_no,
                $event->asset?->property_number, $event->asset?->name, $event->asset?->semi_expendable_value_class?->label(),
                $event->asset?->currentCustodian?->name, $event->from_location, $event->to_location,
                $event->condition_status?->label(), $event->amount, $event->notes, $event->recordedBy?->name,
            ]),
            'summary' => ['transactions' => $events->count()],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableLossReport(array $filters): array
    {
        $events = InventoryAssetEvent::query()
            ->with(['asset.currentCustodian:id,name,code'])
            ->where('event_type', InventoryAssetEventType::Loss)
            ->whereHas('asset', fn (Builder $query) => $query->where('accounting_classification', AssetAccountingClassification::SemiExpendable))
            ->whereDate('event_date', '<=', $filters['as_of'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('event_date', [$filters['from'], $filters['to']]))
            ->orderBy('event_date')->get();
        $deadlineDays = (int) config('inventory.semi_expendable.loss_reporting_deadline_days', 30);

        return [
            'columns' => ['Report No.', 'Incident', 'Discovered', 'Reported', 'Deadline', 'Timeliness', 'Type', 'Property No.', 'Description', 'Accountable person', 'Cost', 'Relief state', 'Security reference', 'Circumstances'],
            'rows' => $events->map(function (InventoryAssetEvent $event) use ($deadlineDays): array {
                $incident = Carbon::parse(data_get($event->details, 'incident_date', $event->event_date));
                $deadline = $incident->copy()->addDays($deadlineDays);
                $timeliness = $event->event_date->lte($deadline) ? 'On time' : 'Overdue';

                return [
                    $event->reference_no, $incident->toDateString(), data_get($event->details, 'date_discovered'),
                    $event->event_date->toDateString(), $deadline->toDateString(), $timeliness,
                    data_get($event->details, 'loss_type'), $event->asset?->property_number, $event->asset?->name,
                    $event->asset?->currentCustodian?->name, $event->asset?->acquisition_cost,
                    $event->asset?->relief_status?->value, data_get($event->details, 'security_reference'),
                    data_get($event->details, 'circumstances', $event->notes),
                ];
            }),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function physicalSemiExpendable(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->get();

        return [
            'columns' => ['Property No.', 'Description', 'Fund cluster', 'Value class', 'Location', 'Accountable person', 'Card qty.', 'Physical qty.', 'Short/(Over)', 'Condition', 'Remarks'],
            'rows' => $assets->map(function (InventoryAsset $asset) use ($filters): array {
                $physical = $this->physicalQuantityAt($asset, $filters['as_of']);

                return [$asset->property_number, $asset->name, $asset->fund_cluster, $asset->semi_expendable_value_class?->label(), $this->locationAt($asset, $filters['as_of']), $this->custodianAt($asset, $filters['as_of']), 1, $physical, 1 - $physical, $this->conditionAt($asset, $filters['as_of']), $asset->physical_count_remarks];
            }),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function icsRenewalReport(array $filters): array
    {
        $asOf = Carbon::parse($filters['as_of']);
        $dueSoon = $asOf->copy()->addDays((int) config('inventory.semi_expendable.renewal_due_soon_days', 90));
        $documents = PropertyAccountabilityDocument::query()->with(['asset', 'supersededBy:id,supersedes_document_id,document_no'])
            ->where('document_type', 'ICS')->whereDate('issued_at', '<=', $asOf)
            ->orderBy('renewal_due_at')->get();

        return [
            'columns' => ['ICS No.', 'Property No.', 'Description', 'Accountable person', 'Issued', 'Renewal due', 'Renewal state', 'Document state', 'Replacement ICS'],
            'rows' => $documents->map(function (PropertyAccountabilityDocument $document) use ($asOf, $dueSoon): array {
                $renewalState = match (true) {
                    $document->status === 'superseded' => 'Renewed / replaced',
                    $document->status === 'cancelled' => 'Cancelled',
                    $document->renewal_due_at?->lt($asOf) => 'Overdue',
                    $document->renewal_due_at?->lte($dueSoon) => 'Due soon',
                    default => 'Active',
                };

                return [$document->document_no, $document->property_number, $document->asset_name, $document->recipient_name, $document->issued_at->toDateString(), $document->renewal_due_at?->toDateString(), $renewalState, $document->status, $document->supersededBy?->document_no];
            }),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function lowValueExpirationReport(array $filters): array
    {
        $asOf = Carbon::parse($filters['as_of']);
        $dueSoon = $asOf->copy()->addDays((int) config('inventory.semi_expendable.expiration_due_soon_days', 90));
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->where('semi_expendable_value_class', SemiExpendableValueClass::Low)
            ->whereNotNull('accountability_expires_at')->orderBy('accountability_expires_at')->get();

        return [
            'columns' => ['Property No.', 'Description', 'Accountable person', 'Expiration', 'Expiration state', 'Accountability state', 'Physical state'],
            'rows' => $assets->map(fn (InventoryAsset $asset): array => [
                $asset->property_number, $asset->name, $asset->currentCustodian?->name, $asset->accountability_expires_at?->toDateString(),
                $asset->accountability_status === SemiExpendableAccountabilityStatus::AccountabilityExtinguished
                    ? 'Extinguished'
                    : ($asset->accountability_expires_at?->lt($asOf) ? 'Due for expiration' : ($asset->accountability_expires_at?->lte($dueSoon) ? 'Due soon' : 'Active')),
                $asset->accountability_status?->label(), $asset->physical_status?->label(),
            ]),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function highValueBeyondUsefulLife(array $filters): array
    {
        $asOf = Carbon::parse($filters['as_of']);
        $documents = PropertyAccountabilityDocument::query()->with('asset.currentCustodian')
            ->where('document_type', 'ICS')->where('value_class', SemiExpendableValueClass::High)
            ->whereIn('status', ['pending_recipient', 'active'])
            ->get()->filter(fn (PropertyAccountabilityDocument $document): bool => $document->issued_at->copy()->addMonths($document->estimated_useful_life_months ?? 0)->lte($asOf));

        return [
            'columns' => ['ICS No.', 'Property No.', 'Description', 'Accountable person', 'Issued', 'Useful life ended', 'Accountability state', 'Control note'],
            'rows' => $documents->map(fn (PropertyAccountabilityDocument $document): array => [
                $document->document_no, $document->property_number, $document->asset_name, $document->recipient_name,
                $document->issued_at->toDateString(), $document->issued_at->copy()->addMonths($document->estimated_useful_life_months ?? 0)->toDateString(),
                $document->asset?->accountability_status?->label(), 'Remains accountable until return or approved relief.',
            ]),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function returnedForReissue(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)
            ->where('available_for_reissue', true)->get();

        return ['columns' => ['Property No.', 'Description', 'Value class', 'Condition', 'Location', 'Unit cost', 'Accounting state'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->property_number, $asset->name, $asset->semi_expendable_value_class?->label(), $asset->condition_status->label(), $asset->location, $asset->acquisition_cost, $asset->accounting_status->label()])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function semiExpendableReconciliation(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)
            ->where('accounting_classification', AssetAccountingClassification::SemiExpendable)->get();

        return [
            'columns' => ['Measure', 'Quantity', 'Explanation'],
            'rows' => [
                ['Received / registered physical items', $assets->count(), 'Individual property records acquired by the reporting date'],
                ['Accounting inventory on hand', $assets->filter(fn (InventoryAsset $asset): bool => $asset->accounting_status === AssetAccountingStatus::OnHand)->count(), 'Not yet issued by the Property Unit'],
                ['Issued and expensed', $assets->filter(fn (InventoryAsset $asset): bool => $asset->accounting_status === AssetAccountingStatus::IssuedAndExpensed)->count(), 'No longer accounting inventory but still physically tracked'],
                ['Active accountability', $assets->filter(fn (InventoryAsset $asset): bool => in_array($asset->accountability_status, [SemiExpendableAccountabilityStatus::PendingRecipient, SemiExpendableAccountabilityStatus::Accountable, SemiExpendableAccountabilityStatus::PendingRelief], true))->count(), 'ICS accountability including pending relief'],
                ['Returned serviceable', $assets->where('available_for_reissue', true)->count(), 'Not re-recognized as accounting inventory'],
                ['Disposed', $assets->where('physical_status', SemiExpendablePhysicalStatus::Disposed)->count(), 'Authorized physical disposal'],
                ['Missing', $assets->where('physical_status', SemiExpendablePhysicalStatus::Missing)->count(), 'Physical property missing; accountability may remain'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryAsset>
     */
    private function assetsForPrint(array $filters): Builder
    {
        return InventoryAsset::query()
            ->with([
                'category',
                'subcategory',
                'currentCustodian:id,name,code',
                'events',
                'custodianAssignments.reference:id,name,code',
                'accountabilityDocuments',
            ])
            ->whereDate('acquisition_date', '<=', $filters['as_of'])
            ->where(fn (Builder $query) => $query
                ->whereNull('derecognized_at')
                ->orWhereDate('derecognized_at', '>', $filters['as_of']))
            ->whereDoesntHave('events', fn (Builder $query) => $query
                ->where('event_type', InventoryAssetEventType::Transfer)
                ->where('details->transfer_evidence->external_ownership_transfer', true)
                ->whereDate('event_date', '<=', $filters['as_of']))
            ->when($filters['fund_cluster'] !== '', fn (Builder $query) => $query->where('fund_cluster', $filters['fund_cluster']))
            ->when($filters['custodian'], fn (Builder $query, int $custodian) => $query->whereHas('custodianAssignments', fn (Builder $assignmentQuery) => $assignmentQuery
                ->where('hris_reference_id', $custodian)
                ->whereDate('assigned_at', '<=', $filters['as_of'])
                ->where(fn (Builder $endQuery) => $endQuery
                    ->whereNull('unassigned_at')
                    ->orWhereDate('unassigned_at', '>', $filters['as_of']))))
            ->orderBy('property_number');
    }

    private function locationAt(InventoryAsset $asset, string $asOf): ?string
    {
        $transfers = $asset->events
            ->where('event_type', InventoryAssetEventType::Transfer)
            ->sortBy('event_date');
        $latest = $transfers
            ->filter(fn (InventoryAssetEvent $event): bool => $event->event_date->lte($asOf))
            ->last();

        return $latest?->to_location
            ?? $transfers->first()?->from_location
            ?? $asset->location;
    }

    private function custodianAt(InventoryAsset $asset, string $asOf): ?string
    {
        return $asset->custodianAssignments
            ->first(fn (InventoryAssetCustodian $assignment): bool => $assignment->assigned_at->lte($asOf.' 23:59:59')
                && ($assignment->unassigned_at === null || $assignment->unassigned_at->gt($asOf.' 23:59:59')))
            ?->reference?->name;
    }

    private function physicalQuantityAt(InventoryAsset $asset, string $asOf): int
    {
        $latestCount = $asset->events
            ->filter(fn (InventoryAssetEvent $event): bool => $event->event_type === InventoryAssetEventType::PhysicalCount
                && $event->event_date->lte($asOf))
            ->sortByDesc('event_date')
            ->first();

        return $latestCount?->quantity ?? $asset->quantity_per_physical_count;
    }

    private function conditionAt(InventoryAsset $asset, string $asOf): string
    {
        $conditionEvent = $asset->events
            ->filter(fn (InventoryAssetEvent $event): bool => $event->event_date->lte($asOf)
                && ($event->condition_status !== null
                    || $event->event_type === InventoryAssetEventType::Unserviceable
                    || data_get($event->details, 'to.condition_status') !== null))
            ->sortByDesc(fn (InventoryAssetEvent $event): string => $event->event_date->format('Y-m-d').str_pad((string) $event->getKey(), 20, '0', STR_PAD_LEFT))
            ->first();
        $value = $conditionEvent?->condition_status?->value
            ?? ($conditionEvent?->event_type === InventoryAssetEventType::Unserviceable
                ? AssetConditionStatus::NonUsable->value
                : data_get($conditionEvent?->details, 'to.condition_status'))
            ?? data_get(
                $asset->events->first(fn (InventoryAssetEvent $event): bool => $event->event_type === InventoryAssetEventType::Registered)?->details,
                'initial_condition_status',
            )
            ?? $asset->condition_status->value;

        return AssetConditionStatus::from($value)->label();
    }

    private function eventDetails(InventoryAssetEvent $event): ?string
    {
        if (blank($event->details)) {
            return null;
        }

        return collect($event->details)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(function (mixed $value, string $key): string {
                $formatted = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $value;

                return str($key)->replace('_', ' ')->title().': '.$formatted;
            })
            ->join('; ');
    }

    /** @param iterable<int, InventoryAsset> $assets @return array<string, string|int> */
    private function valuationSummary(iterable $assets, string $asOf): array
    {
        $gross = BigDecimal::zero();
        $depreciation = BigDecimal::zero();
        $impairment = BigDecimal::zero();
        $carrying = BigDecimal::zero();
        $records = 0;

        foreach ($assets as $asset) {
            $valuation = $this->depreciation->calculate($asset, $asOf);
            $gross = $gross->plus($valuation->grossCarryingAmount);
            $depreciation = $depreciation->plus($valuation->accumulatedDepreciation);
            $impairment = $impairment->plus($valuation->impairmentLosses);
            $carrying = $carrying->plus($valuation->carryingAmount);
            $records++;
        }

        return [
            'records' => $records,
            'gross amount' => (string) $gross->toScale(2, RoundingMode::HalfUp),
            'accumulated depreciation' => (string) $depreciation->toScale(2, RoundingMode::HalfUp),
            'impairment' => (string) $impairment->toScale(2, RoundingMode::HalfUp),
            'carrying amount' => (string) $carrying->toScale(2, RoundingMode::HalfUp),
        ];
    }

    /**
     * @return array{
     *     consumable_types: int,
     *     stock_on_hand: int,
     *     low_stock: int,
     *     consumable_value: float,
     *     expiring_value: float,
     *     expired_value: float,
     *     asset_count: int,
     *     acquisition_cost: float,
     *     depreciation: float,
     *     book_value: float
     * }
     */
    public function summary(): array
    {
        $itemSummary = (array) InventoryItem::query()
            ->where('status', 'active')
            ->toBase()
            ->selectRaw('COUNT(*) AS consumable_types')
            ->selectRaw('COALESCE(SUM(quantity), 0) AS stock_on_hand')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity <= reorder_point THEN 1 ELSE 0 END), 0) AS low_stock')
            ->first();

        $today = today()->toDateString();
        $warningDate = today()->addDays(InventoryItem::EXPIRATION_WARNING_DAYS)->toDateString();
        $batchSummary = (array) InventoryItemBatch::query()
            ->join('inventory_items', 'inventory_items.inventory_item_id', '=', 'inventory_item_batches.inventory_item_id')
            ->whereNull('inventory_items.deleted_at')
            ->where('inventory_items.status', 'active')
            ->where('inventory_item_batches.quantity_remaining', '>', 0)
            ->toBase()
            ->selectRaw('COALESCE(SUM(quantity_remaining * unit_cost), 0) AS consumable_value')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN expiration_date BETWEEN ? AND ? THEN quantity_remaining * unit_cost ELSE 0 END), 0) AS expiring_value',
                [$today, $warningDate],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN expiration_date < ? THEN quantity_remaining * unit_cost ELSE 0 END), 0) AS expired_value',
                [$today],
            )
            ->first();

        $assetSummary = [
            'asset_count' => 0,
            'acquisition_cost' => 0.0,
            'depreciation' => 0.0,
            'book_value' => 0.0,
        ];

        foreach (InventoryAsset::query()
            ->with('events')
            ->select([
                'inventory_asset_id',
                'acquisition_date',
                'available_for_use_date',
                'acquisition_cost',
                'directly_attributable_costs',
                'accounting_classification',
                'residual_value_percentage',
                'depreciation_useful_life_months',
                'is_depreciable',
                'impairment_losses',
                'derecognized_at',
            ])
            ->lazyById(250, column: 'inventory_asset_id') as $asset) {
            $assetSummary['asset_count']++;
            $assetSummary['acquisition_cost'] += (float) ($asset->acquisition_cost ?? 0);
            $assetSummary['depreciation'] += $asset->depreciation_amount;
            $assetSummary['book_value'] += $asset->book_value;
        }

        return [
            'consumable_types' => (int) ($itemSummary['consumable_types'] ?? 0),
            'stock_on_hand' => (int) ($itemSummary['stock_on_hand'] ?? 0),
            'low_stock' => (int) ($itemSummary['low_stock'] ?? 0),
            'consumable_value' => round((float) ($batchSummary['consumable_value'] ?? 0), 2),
            'expiring_value' => round((float) ($batchSummary['expiring_value'] ?? 0), 2),
            'expired_value' => round((float) ($batchSummary['expired_value'] ?? 0), 2),
            'asset_count' => $assetSummary['asset_count'],
            'acquisition_cost' => round($assetSummary['acquisition_cost'], 2),
            'depreciation' => round($assetSummary['depreciation'], 2),
            'book_value' => round($assetSummary['book_value'], 2),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return LengthAwarePaginator<int, InventoryItem>
     */
    public function consumables(array $filters): LengthAwarePaginator
    {
        return $this->consumableQuery($filters)
            ->with(['seriesCategory.classCategory.majorCategory', 'batches'])
            ->latest('inventory_item_id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, string>  $filters
     * @return LengthAwarePaginator<int, InventoryAsset>
     */
    public function assets(array $filters): LengthAwarePaginator
    {
        return $this->assetQuery($filters)
            ->with(['category', 'subcategory', 'currentCustodian:id,name,code', 'activeBorrowing.borrowerReference:id,name,code', 'events'])
            ->latest('inventory_asset_id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return array{
     *     lifecycles: array<int, array{value: string, label: string}>,
     *     conditions: array<int, array{value: string, label: string}>,
     *     custody: array<int, array{value: string, label: string}>
     * }
     */
    public function options(): array
    {
        return [
            'lifecycles' => array_map(
                fn (AssetLifecycleStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AssetLifecycleStatus::cases(),
            ),
            'conditions' => array_map(
                fn (AssetConditionStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AssetConditionStatus::cases(),
            ),
            'custody' => array_map(
                fn (AssetCustodyStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                AssetCustodyStatus::cases(),
            ),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{filename: string, headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function export(array $filters): array
    {
        if ($filters['report'] === 'assets') {
            return [
                'filename' => 'asset-register-'.now()->format('Y-m-d-His').'.csv',
                'headers' => [
                    'Property number', 'Serial number', 'Name', 'Category', 'Subcategory', 'Custody', 'Custodian / borrower',
                    'Lifecycle', 'Condition', 'Location', 'Acquisition date', 'Acquisition cost', 'Accumulated depreciation',
                    'Book value', 'Impairment losses', 'Archived at',
                ],
                'rows' => $this->assetExportRows($filters),
            ];
        }

        return [
            'filename' => 'consumable-inventory-'.now()->format('Y-m-d-His').'.csv',
            'headers' => [
                'Stock number', 'Name', 'Major category', 'Class category', 'Series category', 'Unit', 'On hand',
                'Reorder point', 'Suggested reorder', 'Stock value', 'Next expiration', 'Attention', 'Status', 'Archived at',
            ],
            'rows' => $this->consumableExportRows($filters),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<InventoryItem>
     */
    private function consumableQuery(array $filters): Builder
    {
        $query = InventoryItem::query()
            ->when($filters['records'] === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', $search)
                    ->orWhere('stock_number', 'like', $search));
            })
            ->when($filters['item_status'] !== '', fn (Builder $query) => $query->where('status', $filters['item_status']))
            ->when($filters['attention'] === 'low_stock', fn (Builder $query) => $query->lowStock());

        if (in_array($filters['attention'], ['expired', 'expiring'], true)) {
            $batches = InventoryItemBatch::query()
                ->select('inventory_item_id')
                ->where('quantity_remaining', '>', 0);

            if ($filters['attention'] === 'expired') {
                $batches->whereDate('expiration_date', '<', today());
            } else {
                $batches->whereBetween('expiration_date', [
                    today(),
                    today()->addDays(InventoryItem::EXPIRATION_WARNING_DAYS),
                ]);
            }

            $query->whereIn('inventory_item_id', $batches);
        }

        return $query;
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<InventoryAsset>
     */
    private function assetQuery(array $filters): Builder
    {
        $borrowedAssetIds = InventoryAssetBorrowing::query()
            ->select('inventory_asset_id')
            ->where('status', 'borrowed');

        return InventoryAsset::query()
            ->when($filters['records'] === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('property_number', 'like', $search));
            })
            ->when($filters['lifecycle_status'] !== '', fn (Builder $query) => $query->where('lifecycle_status', $filters['lifecycle_status']))
            ->when($filters['condition_status'] !== '', fn (Builder $query) => $query->where('condition_status', $filters['condition_status']))
            ->when($filters['custody_status'] === AssetCustodyStatus::Borrowed->value, fn (Builder $query) => $query->whereIn('inventory_asset_id', $borrowedAssetIds))
            ->when($filters['custody_status'] === AssetCustodyStatus::Assigned->value, fn (Builder $query) => $query
                ->whereNotNull('current_custodian_reference_id')
                ->whereNotIn('inventory_asset_id', $borrowedAssetIds))
            ->when($filters['custody_status'] === AssetCustodyStatus::Available->value, fn (Builder $query) => $query
                ->whereNull('current_custodian_reference_id')
                ->whereNotIn('inventory_asset_id', $borrowedAssetIds));
    }

    /**
     * @param  array<string, string>  $filters
     * @return LazyCollection<int, array<int, mixed>>
     */
    private function consumableExportRows(array $filters): LazyCollection
    {
        return $this->consumableQuery($filters)
            ->with(['seriesCategory.classCategory.majorCategory', 'batches'])
            ->lazyById(250, column: 'inventory_item_id')
            ->map($this->consumableExportRow(...));
    }

    /**
     * @param  array<string, string>  $filters
     * @return LazyCollection<int, array<int, mixed>>
     */
    private function assetExportRows(array $filters): LazyCollection
    {
        return $this->assetQuery($filters)
            ->with(['category', 'subcategory', 'currentCustodian:id,name,code', 'activeBorrowing.borrowerReference:id,name,code', 'events'])
            ->lazyById(250, column: 'inventory_asset_id')
            ->map($this->assetExportRow(...));
    }

    /** @return array<int, mixed> */
    private function consumableExportRow(InventoryItem $item): array
    {
        return [
            $item->stock_number,
            $item->name,
            $item->seriesCategory?->classCategory?->majorCategory?->name,
            $item->seriesCategory?->classCategory?->name,
            $item->seriesCategory?->name,
            $item->unit_of_measure,
            $item->quantity,
            $item->reorder_point,
            $item->reorder_quantity,
            $item->inventory_value,
            $item->next_expiration_date,
            $item->is_low_stock ? 'Low stock' : match ($item->expiration_status) {
                'expired' => 'Expired stock',
                'expiring' => 'Expiring soon',
                default => '',
            },
            $item->status,
            $item->deleted_at?->toDateTimeString(),
        ];
    }

    /** @return array<int, mixed> */
    private function assetExportRow(InventoryAsset $asset): array
    {
        $borrowing = $asset->activeBorrowing;
        $holder = $borrowing === null
            ? $asset->currentCustodian?->name
            : ($borrowing->borrower_reference_id === null
                ? $borrowing->borrower_name
                : $borrowing->borrowerReference->name);

        return [
            $asset->property_number,
            $asset->serial_number,
            $asset->name,
            $asset->category?->name,
            $asset->subcategory?->name,
            $asset->custody_status,
            $holder,
            $asset->lifecycle_status->label(),
            $asset->condition_status->label(),
            $asset->location,
            $asset->acquisition_date?->toDateString(),
            $asset->acquisition_cost,
            $asset->depreciation_amount,
            $asset->book_value,
            $asset->impairment_losses,
            $asset->deleted_at?->toDateTimeString(),
        ];
    }
}
