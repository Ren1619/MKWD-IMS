<?php

namespace App\Services;

use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryAssetCustodian;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class InventoryReportService
{
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
            'rpci' => $this->physicalInventories(),
            'variance-reconciliation' => $this->varianceReconciliation($filters),
            'par', 'ics', 'property-return', 'accountability-employee', 'accountability-office' => $this->assetAccountability($filters),
            'property-card' => $this->propertyCards($filters),
            'ppe-ledger-card', 'depreciation-schedule' => $this->ppeLedger($filters),
            'stock-card', 'supplies-ledger-card' => $this->stockLedger(),
            'ris', 'rsmi' => $this->issuedSupplies($filters),
            'iar', 'receipt-register' => $this->receivedSupplies($filters),
            'property-transfer' => $this->propertyTransfers($filters),
            'iirup' => $this->unserviceableProperty($filters),
            'wmr', 'disposal-register' => $this->disposedProperty($filters),
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
            'accountability-employee' => ['code' => 'AAE', 'title' => 'Property Accountability per Employee', 'group' => 'Accountability', 'description' => 'All property assigned to each employee.'],
            'accountability-office' => ['code' => 'AAO', 'title' => 'Property Accountability per Office or Location', 'group' => 'Accountability', 'description' => 'Property grouped for location inspection.'],
            'property-card' => ['code' => 'PC', 'title' => 'Property Card', 'group' => 'Cards and ledgers', 'description' => 'Property-unit PPE record.'],
            'ppe-ledger-card' => ['code' => 'PPELC', 'title' => 'Property, Plant and Equipment Ledger Card', 'group' => 'Cards and ledgers', 'description' => 'Accounting PPE cost and carrying amounts.'],
            'stock-card' => ['code' => 'SC', 'title' => 'Stock Card', 'group' => 'Cards and ledgers', 'description' => 'Receipts, issuances and running stock balance.'],
            'supplies-ledger-card' => ['code' => 'SLC', 'title' => 'Supplies Ledger Card', 'group' => 'Cards and ledgers', 'description' => 'Quantity and monetary inventory ledger.'],
            'depreciation-schedule' => ['code' => 'DS', 'title' => 'PPE Depreciation and Lapsing Schedule', 'group' => 'Cards and ledgers', 'description' => 'Cost, useful life, depreciation and carrying amount.'],
            'ris' => ['code' => 'RIS', 'title' => 'Requisition and Issue Slip Register', 'group' => 'Receipts and issues', 'description' => 'Traceable inventory issuances by RIS number.'],
            'rsmi' => ['code' => 'RSMI', 'title' => 'Report of Supplies and Materials Issued', 'group' => 'Receipts and issues', 'description' => 'Period summary of issued supplies.'],
            'iar' => ['code' => 'IAR', 'title' => 'Inspection and Acceptance Report Register', 'group' => 'Receipts and issues', 'description' => 'Accepted deliveries and source references.'],
            'receipt-register' => ['code' => 'RR', 'title' => 'Inventory Receipt Register', 'group' => 'Receipts and issues', 'description' => 'Receipt postings supporting stock and ledger cards.'],
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
        $assets = $this->assetsForPrint($filters)->get();

        return ['columns' => ['Property No.', 'Description', 'Location', 'Accountable person', 'Unit', 'Card qty.', 'Physical qty.', 'Short/(Over)', 'Unit cost', 'Condition', 'Remarks'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [
            $asset->property_number, $asset->name, $asset->location, $asset->currentCustodian?->name, $asset->unit_of_measure,
            $asset->quantity_per_property_card, $asset->quantity_per_physical_count,
            $asset->quantity_per_property_card - $asset->quantity_per_physical_count,
            $asset->acquisition_cost, $asset->condition_status->label(), $asset->physical_count_remarks,
        ]), 'summary' => ['records' => $assets->count(), 'acquisition cost' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost)]];
    }

    /** @return array<string, mixed> */
    private function physicalInventories(): array
    {
        $items = InventoryItem::query()->with('batches')->orderBy('stock_number')->get();

        return ['columns' => ['Stock No.', 'Article / Description', 'Unit', 'Unit value', 'Balance per card', 'On hand per count', 'Short/(Over)', 'Value', 'Remarks'], 'rows' => $items->map(fn (InventoryItem $item): array => [
            $item->stock_number, $item->name, $item->unit_of_measure, $this->averageUnitCost($item), $item->quantity, $item->quantity, 0, $item->inventory_value, null,
        ]), 'summary' => ['records' => $items->count(), 'inventory value' => $items->sum(fn (InventoryItem $item): float => (float) $item->inventory_value)]];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function varianceReconciliation(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->get()->filter(fn (InventoryAsset $asset): bool => $asset->quantity_per_property_card !== $asset->quantity_per_physical_count);

        return ['columns' => ['Type', 'Reference', 'Description', 'Book/Card balance', 'Physical count', 'Variance', 'Unit value', 'Variance value', 'Explanation / action'], 'rows' => $assets->map(function (InventoryAsset $asset): array {
            $variance = $asset->quantity_per_physical_count - $asset->quantity_per_property_card;

            return ['PPE', $asset->property_number, $asset->name, $asset->quantity_per_property_card, $asset->quantity_per_physical_count, $variance, $asset->acquisition_cost, $variance * (float) $asset->acquisition_cost, $asset->physical_count_remarks];
        })];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function assetAccountability(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->whereNotNull('current_custodian_reference_id')->get();

        return ['columns' => ['Accountable person', 'Employee No.', 'Property No.', 'Description', 'Serial No.', 'Unit', 'Qty.', 'Acquisition date', 'Amount', 'Location', 'Condition'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [
            $asset->currentCustodian?->name, $asset->currentCustodian?->code, $asset->property_number, $asset->name, $asset->serial_number,
            $asset->unit_of_measure, $asset->quantity_per_property_card, $asset->acquisition_date?->toDateString(), $asset->acquisition_cost, $asset->location, $asset->condition_status->label(),
        ]), 'summary' => ['records' => $assets->count(), 'amount' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost)]];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function propertyCards(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->get();

        return ['columns' => ['Property No.', 'Description', 'Category', 'Serial No.', 'Acquired', 'Reference', 'Custodian', 'Location', 'Cost', 'Status'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->property_number, $asset->name, $asset->category?->name, $asset->serial_number, $asset->acquisition_date?->toDateString(), null, $asset->currentCustodian?->name, $asset->location, $asset->acquisition_cost, $asset->lifecycle_status->label()])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function ppeLedger(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->get();

        return ['columns' => ['Property No.', 'PPE class', 'Description', 'Acquisition date', 'Cost', 'Useful life (months)', 'Accumulated depreciation', 'Impairment', 'Carrying amount'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->property_number, $asset->category?->name, $asset->name, $asset->acquisition_date?->toDateString(), $asset->acquisition_cost, $asset->depreciation_useful_life_months, $asset->depreciation_amount, $asset->impairment_losses, $asset->book_value]), 'summary' => ['cost' => $assets->sum(fn (InventoryAsset $asset): float => (float) $asset->acquisition_cost), 'depreciation' => $assets->sum('depreciation_amount'), 'carrying amount' => $assets->sum('book_value')]];
    }

    /** @return array<string, mixed> */
    private function stockLedger(): array
    {
        $rows = collect();
        InventoryItem::query()->with(['batches', 'stockOuts.allocations'])->orderBy('stock_number')->each(function (InventoryItem $item) use ($rows): void {
            foreach ($item->batches as $batch) {
                $rows->push([$item->stock_number, $item->name, $batch->received_at->toDateString(), $batch->reference_no, 'Receipt', $batch->quantity_in, null, $batch->unit_cost, $batch->quantity_in * (float) $batch->unit_cost]);
            }
            foreach ($item->stockOuts as $issue) {
                $rows->push([$item->stock_number, $item->name, $issue->stocked_out_at->toDateString(), $issue->ris_no, 'Issue', null, $issue->quantity, $issue->quantity > 0 ? (float) $issue->total_cost / $issue->quantity : 0, $issue->total_cost]);
            }
        });

        return ['columns' => ['Stock No.', 'Article', 'Date', 'Reference', 'Transaction', 'Received', 'Issued', 'Unit cost', 'Amount'], 'rows' => $rows->sortBy(fn (array $row): string => $row[0].$row[2])->values()];
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

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function receivedSupplies(array $filters): array
    {
        $batches = InventoryItemBatch::query()
            ->with('item')
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('received_at', [$filters['from'], $filters['to']]))
            ->orderBy('received_at')
            ->get();

        return ['columns' => ['Date received', 'Reference / IAR', 'Source / Supplier', 'Stock No.', 'Description', 'Unit', 'Qty. accepted', 'Unit cost', 'Amount', 'Inspection remarks'], 'rows' => $batches->map(fn (InventoryItemBatch $batch): array => [$batch->received_at->toDateString(), $batch->reference_no, $batch->source, $batch->item?->stock_number, $batch->item?->name, $batch->item?->unit_of_measure, $batch->quantity_in, $batch->unit_cost, $batch->quantity_in * (float) $batch->unit_cost, $batch->notes]), 'summary' => ['quantity received' => $batches->sum('quantity_in'), 'amount' => $batches->sum(fn (InventoryItemBatch $batch): float => $batch->quantity_in * (float) $batch->unit_cost)]];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function propertyTransfers(array $filters): array
    {
        $assignments = InventoryAssetCustodian::query()
            ->with(['asset', 'reference'])
            ->when($filters['from'] && $filters['to'], fn (Builder $query) => $query->whereBetween('assigned_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']))
            ->orderBy('assigned_at')
            ->get();

        return ['columns' => ['Transfer date', 'Property No.', 'Description', 'New accountable person', 'Employee No.', 'Released/ended', 'Location', 'Remarks'], 'rows' => $assignments->map(fn (InventoryAssetCustodian $assignment): array => [$assignment->assigned_at->toDateString(), $assignment->asset?->property_number, $assignment->asset?->name, $assignment->reference?->name, $assignment->reference?->code, $assignment->unassigned_at?->toDateString(), $assignment->asset?->location, null])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function unserviceableProperty(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->where(fn (Builder $query) => $query->where('condition_status', AssetConditionStatus::NonUsable->value)->orWhere('lifecycle_status', AssetLifecycleStatus::Retired->value))->get();

        return ['columns' => ['Property No.', 'Description', 'Acquired', 'Cost', 'Accumulated depreciation', 'Book value', 'Condition', 'Location', 'Appraised value', 'Disposal recommendation'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->property_number, $asset->name, $asset->acquisition_date?->toDateString(), $asset->acquisition_cost, $asset->depreciation_amount, $asset->book_value, $asset->condition_status->label(), $asset->location, $asset->appraised_value, $asset->disposal_method])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function disposedProperty(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->where('lifecycle_status', AssetLifecycleStatus::Disposed->value)->get();

        return ['columns' => ['Property No.', 'Description / waste material', 'Cost', 'Accumulated depreciation', 'Book value', 'Method', 'Appraised value', 'Proceeds / disposal value', 'Gain/(Loss)', 'Remarks'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->property_number, $asset->name, $asset->acquisition_cost, $asset->depreciation_amount, $asset->book_value, $asset->disposal_method, $asset->appraised_value, $asset->disposal_value, (float) $asset->disposal_value - $asset->book_value, $asset->physical_count_remarks])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function lostProperty(array $filters): array
    {
        $assets = $this->assetsForPrint($filters)->whereNotNull('loss_report_no')->get();

        return ['columns' => ['Report No.', 'Report date', 'Type', 'Property No.', 'Description', 'Accountable person', 'Acquisition cost', 'Book value', 'Circumstances'], 'rows' => $assets->map(fn (InventoryAsset $asset): array => [$asset->loss_report_no, $asset->loss_report_date?->toDateString(), $asset->loss_type, $asset->property_number, $asset->name, $asset->currentCustodian?->name, $asset->acquisition_cost, $asset->book_value, $asset->loss_circumstances])];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function auditExceptions(array $filters): array
    {
        $rows = collect();
        $this->assetsForPrint($filters)->get()->each(function (InventoryAsset $asset) use ($rows): void {
            if ($asset->property_number === null) {
                $rows->push(['PPE', $asset->serial_number, $asset->name, 'Missing property number', 'Assign and tag before count']);
            }
            if ($asset->current_custodian_reference_id === null && $asset->lifecycle_status === AssetLifecycleStatus::Active) {
                $rows->push(['PPE', $asset->property_number, $asset->name, 'No accountable custodian', 'Issue PAR/ICS or document storage custody']);
            }
            if ($asset->quantity_per_property_card !== $asset->quantity_per_physical_count) {
                $rows->push(['PPE', $asset->property_number, $asset->name, 'Physical count variance', 'Investigate and reconcile']);
            }
        });
        InventoryItemStockOut::query()->with('item')->whereNull('ris_no')->each(fn (InventoryItemStockOut $issue) => $rows->push(['Inventory', $issue->getKey(), $issue->item?->name, 'Issue without RIS number', 'Complete source document reference']));
        InventoryItemBatch::query()->with('item')->whereNull('reference_no')->each(fn (InventoryItemBatch $batch) => $rows->push(['Inventory', $batch->getKey(), $batch->item?->name, 'Receipt without IAR/reference number', 'Complete receipt source document']));

        return ['columns' => ['Record type', 'Reference', 'Description', 'Exception', 'Required action'], 'rows' => $rows, 'summary' => ['exceptions' => $rows->count()]];
    }

    /** @param array<string, mixed> $filters @return Builder<InventoryAsset> */
    private function assetsForPrint(array $filters): Builder
    {
        return InventoryAsset::query()->with(['category', 'currentCustodian:id,name,code'])->when($filters['fund_cluster'] !== '', fn (Builder $query) => $query->where('fund_cluster', $filters['fund_cluster']))->when($filters['custodian'], fn (Builder $query, int $custodian) => $query->where('current_custodian_reference_id', $custodian))->orderBy('property_number');
    }

    private function averageUnitCost(InventoryItem $item): float
    {
        $quantity = $item->batches->sum('quantity_remaining');

        return $quantity > 0 ? round($item->batches->sum(fn (InventoryItemBatch $batch): float => $batch->quantity_remaining * (float) $batch->unit_cost) / $quantity, 2) : 0;
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
            ->select([
                'inventory_asset_id',
                'acquisition_date',
                'acquisition_cost',
                'depreciation_useful_life_months',
            ])
            ->cursor() as $asset) {
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
            ->with(['category', 'currentCustodian:id,name,code', 'activeBorrowing.borrowerReference:id,name,code'])
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
                    'Property number', 'Serial number', 'Name', 'Category', 'Type', 'Custody', 'Custodian / borrower',
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
            ->with(['category', 'currentCustodian:id,name,code', 'activeBorrowing.borrowerReference:id,name,code'])
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
            $asset->type,
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
