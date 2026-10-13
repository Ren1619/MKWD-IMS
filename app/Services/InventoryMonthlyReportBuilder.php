<?php

namespace App\Services;

use App\Models\DepartmentLiquidation;
use App\Models\InventoryCountAdjustment;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use App\Models\InventoryItemStockOutAllocation;
use App\Models\InventoryReturn;
use App\Models\ProcurementDelivery;
use App\Models\ProcurementRequest;
use App\Models\PropertyAccountabilityDocument;
use App\Models\SupplyRequest;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

class InventoryMonthlyReportBuilder
{
    public function __construct(private InventoryReportService $reports) {}

    /** @return array<string,string> */
    public static function catalog(): array
    {
        return ['iar' => 'Inspection and Acceptance Report Register', 'receipts' => 'Receiving Report Register', 'issues' => 'Materials Issuance Slip Register',
            'issue-journal' => 'Material Issued Journal', 'returns' => 'Returned Material Slip Register', 'return-journal' => 'Material Returned Journal',
            'purchase-orders' => 'Purchase Order Register', 'job-orders' => 'Job Order Register', 'equipment' => 'Acknowledgement Receipt for Equipment (PAR) Register',
            'ics' => 'Inventory Custodian Slip Register', 'stock-master' => 'Stock Master List', 'adjustments' => 'Approved Inventory Adjustment Memorandum Register'];
    }

    /**
     * @return array{payload:array{entity_name:string,from:string,to:string,reports:array<string,array{title:string,columns:list<string>,rows:list<list<string|int|null>>}>,reconciliation:list<array<string,mixed>>},readiness:array{blockers:list<array<string,mixed>>,totals:array<string,int|string>},hash:string}
     */
    public function build(string $month, ?int $categoryId, bool $includeZero, string $fundCluster): array
    {
        $from = Carbon::createFromFormat('!Y-m', $month, 'Asia/Manila')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $filters = ['as_of' => $to->toDateString(), 'period' => 'custom', 'period_label' => $from->format('F Y'), 'year' => $from->year, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'fund_cluster' => $fundCluster, 'custodian' => null];
        $reports = [];
        foreach (['iar' => 'iar', 'receipts' => 'receipt-register', 'returns' => 'return-register', 'return-journal' => 'return-journal'] as $key => $document) {
            $table = $this->reports->printable($document, $filters);
            $rows = [];
            if (! is_iterable($table['rows'])) {
                throw new \LogicException('Monthly registers require iterable report rows.');
            }
            foreach ($table['rows'] as $row) {
                if (! is_array($row)) {
                    throw new \LogicException('Monthly registers require array report rows.');
                }
                $normalized = array_values(array_map($this->cell(...), $row));
                if ($key === 'receipts' && is_int($normalized[6] ?? null) && is_string($normalized[7] ?? null)) {
                    $normalized[8] = (string) BigDecimal::of($normalized[7])->multipliedBy($normalized[6]);
                }
                $rows[] = $normalized;
            }
            $reports[$key] = $this->table($key, $table['columns'], $rows);
        }
        $allocations = InventoryItemStockOutAllocation::query()->with(['stockOut.item' => fn ($query) => $query->withTrashed(), 'stockOut.issuanceSlip'])
            ->whereHas('stockOut', fn ($query) => $query->whereDate('stocked_out_at', '>=', $from->toDateString())->whereDate('stocked_out_at', '<=', $to->toDateString()))->orderBy('inventory_item_stock_out_allocation_id')->get();
        $issueRows = [];
        foreach ($allocations as $allocation) {
            $issue = $allocation->stockOut;
            $issueRows[] = [$issue->stocked_out_at->toDateString(), $issue->issuanceSlip->slip_no ?? $issue->ris_no ?? 'ISSUE-'.$issue->getKey(), $issue->item->stock_number ?? null, $issue->item->name ?? null,
                $issue->item->unit_of_measure ?? null, $allocation->quantity, $allocation->unit_cost, (string) BigDecimal::of($allocation->unit_cost)->multipliedBy($allocation->quantity),
                $issue->recipient_name, $issue->department_name, $issue->responsibility_center_code];
        }
        $issueColumns = ['Issue date', 'RIS / IS reference', 'Stock number', 'Description', 'Unit', 'Quantity', 'FIFO unit cost', 'Amount', 'Recipient', 'Department', 'Responsibility center'];
        $reports['issues'] = $this->table('issues', $issueColumns, $issueRows);
        $reports['issue-journal'] = $this->table('issue-journal', $issueColumns, $issueRows);
        $orderRows = ['PR' => [], 'JO' => []];
        $orders = ProcurementRequest::query()->withTrashed()->with(['lines', 'actions' => fn ($query) => $query->whereIn('action', ['order', 'forward'])->orderBy('id')])
            ->whereHas('actions', fn ($query) => $query->whereIn('action', ['order', 'forward'])->whereBetween('created_at', [$from->copy()->timezone(config('app.timezone')), $to->copy()->timezone(config('app.timezone'))]))->orderBy('id')->get();
        foreach ($orders as $order) {
            $action = $order->actions->first(fn ($entry): bool => $entry->action === ($order->document_kind === 'JO' ? 'forward' : 'order'));
            if ($action === null || ! $action->created_at->copy()->timezone('Asia/Manila')->betweenIncluded($from, $to)) {
                continue;
            }
            $amount = BigDecimal::of('0.00');
            foreach ($order->lines as $line) {
                $amount = $amount->plus(BigDecimal::of($line->estimated_unit_cost)->multipliedBy($line->quantity));
            }
            $orderRows[$order->document_kind][] = [$action->created_at->copy()->timezone('Asia/Manila')->toDateString(), $order->pr_no,
                $order->document_kind === 'JO' ? null : ($action->metadata['purchase_order_no'] ?? $order->purchase_order_no),
                $order->purpose, $order->funding_source, (string) $amount, $order->document_kind === 'JO' ? 'Endorsed' : 'Ordered', $action->metadata['endorsement_recipient'] ?? null];
        }
        foreach (['purchase-orders' => 'PR', 'job-orders' => 'JO'] as $key => $kind) {
            $reports[$key] = $this->table($key, ['Order / endorsement date', 'PR / JO number', 'PO number', 'Purpose', 'Funding source', 'Approved requisition estimate', 'Registered stage', 'Endorsement recipient'], $orderRows[$kind]);
        }
        $accountabilityRows = ['equipment' => [], 'ics' => []];
        $documents = PropertyAccountabilityDocument::query()->whereNotNull('acknowledged_at')->whereBetween('acknowledged_at', [$from->copy()->timezone(config('app.timezone')), $to->copy()->timezone(config('app.timezone'))])->orderBy('id')->get();
        foreach ($documents as $document) {
            if ($document->status === 'cancelled') {
                continue;
            }
            $key = $document->document_type === 'ICS' ? 'ics' : 'equipment';
            $accountabilityRows[$key][] = [$document->acknowledged_at->copy()->timezone('Asia/Manila')->toDateString(), $document->document_no, $document->document_type,
                $document->property_number, $document->asset_name, $document->unit_of_measure, $document->quantity, (string) $document->acquisition_cost,
                (string) BigDecimal::of($document->acquisition_cost)->multipliedBy($document->quantity), $document->recipient_name, $document->recipient_office, $document->fund_cluster];
        }
        foreach ($accountabilityRows as $key => $rows) {
            $reports[$key] = $this->table($key, ['Acknowledged date', 'Document', 'Type', 'Property number', 'Description', 'Unit', 'Quantity', 'Unit cost', 'Amount', 'Recipient', 'Office', 'Fund cluster'], $rows);
        }
        $adjustments = InventoryCountAdjustment::query()->with('line.session')->whereBetween('created_at', [$from->copy()->timezone(config('app.timezone')), $to->copy()->timezone(config('app.timezone'))])->orderBy('id')->get();
        $adjustmentRows = [];
        foreach ($adjustments as $adjustment) {
            $adjustmentRows[] = [$adjustment->created_at->copy()->timezone('Asia/Manila')->toDateString(), $adjustment->line->session->document_no,
                $adjustment->line->session->memorandum_reference, $adjustment->line->stock_number, $adjustment->line->item_name, $adjustment->quantity,
                $adjustment->unit_cost, (string) BigDecimal::of($adjustment->unit_cost)->multipliedBy($adjustment->quantity)];
        }
        $reports['adjustments'] = $this->table('adjustments', ['Posting date', 'Approved count / IAM reference', 'Memorandum', 'Stock number', 'Description', 'Signed adjustment quantity', 'FIFO unit cost', 'Signed amount'], $adjustmentRows);
        $blockers = $this->pending($to);
        [$stockRows,$reconciliation,$stockValue] = $this->stockMaster($from, $to, $categoryId, $includeZero, $blockers);
        $reports['stock-master'] = $this->table('stock-master', ['Stock number', 'Description', 'Classification', 'Unit', 'Opening quantity', 'Receipts', 'Returns', 'Issued', 'Adjustments', 'Closing quantity', 'Closing FIFO value'], $stockRows);
        $ordered = [];
        foreach (self::catalog() as $key => $name) {
            $ordered[$key] = $reports[$key];
        }
        $payload = ['entity_name' => config('app.name'), 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'reports' => $ordered, 'reconciliation' => $reconciliation];
        $readiness = ['blockers' => $blockers, 'totals' => ['stock_master_rows' => count($stockRows), 'closing_stock_value' => $stockValue, 'issues' => count($issueRows), 'adjustments' => count($adjustmentRows), 'reports' => count($ordered)]];

        return ['payload' => $payload, 'readiness' => $readiness, 'hash' => hash('sha256', json_encode([$payload, $readiness], JSON_THROW_ON_ERROR))];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|null>>  $rows
     * @return array{title:string,columns:list<string>,rows:list<list<string|int|null>>}
     */
    private function table(string $key, array $columns, array $rows): array
    {
        return ['title' => self::catalog()[$key], 'columns' => $columns, 'rows' => $rows];
    }

    private function cell(mixed $value): string|int|null
    {
        if ($value === null || is_int($value) || is_string($value)) {
            return $value;
        }

        return is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
    }

    /** @return list<array<string,mixed>> */
    private function pending(Carbon $to): array
    {
        $blockers = [];
        $sources = [
            ['Supply request', SupplyRequest::query()->where('status', 'submitted')->where('created_at', '<=', $to->copy()->timezone(config('app.timezone'))), 'ris_no'],
            ['Delivery inspection', ProcurementDelivery::query()->whereIn('status', ['received', 'inspected'])->where('received_at', '<=', $to->copy()->timezone(config('app.timezone'))), 'document_no'],
            ['Return to stock', InventoryReturn::query()->whereIn('status', ['submitted', 'verified'])->whereDate('return_date', '<=', $to->toDateString()), 'document_no'],
            ['Physical count', InventoryCountSession::query()->whereIn('status', ['counting', 'submitted', 'verified'])->where('cutoff_at', '<=', $to->copy()->timezone(config('app.timezone'))), 'document_no'],
            ['Property acknowledgement', PropertyAccountabilityDocument::query()->where('status', 'pending_recipient')->where('issued_at', '<=', $to->copy()->timezone(config('app.timezone'))), 'document_no'],
            ['IS liquidation', DepartmentLiquidation::query()->whereIn('status', ['submitted', 'checked'])->whereDate('liquidation_date', '<=', $to->toDateString()), 'document_no'],
            ['Legacy delivery', ProcurementRequest::query()->where('status', 'delivered')->whereDoesntHave('deliveries')->whereHas('lines', fn ($query) => $query->whereDate('received_at', '<=', $to->toDateString())), 'pr_no'],
        ];
        foreach ($sources as [$kind,$query,$reference]) {
            foreach ($query->orderBy($query->getModel()->getKeyName())->get() as $record) {
                $blockers[] = ['kind' => $kind, 'reference' => $record->getAttribute($reference), 'status' => $record->getAttribute('status'), 'reason' => 'Complete approval, rejection, or cancellation before monthly sign-off.'];
            }
        }

        return $blockers;
    }

    /**
     * @param  list<array<string,mixed>>  $blockers
     * @return array{list<list<string|int|null>>,list<array<string,mixed>>,string}
     */
    private function stockMaster(Carbon $from, Carbon $to, ?int $categoryId, bool $includeZero, array &$blockers): array
    {
        $items = InventoryItem::query()->withTrashed()->with(['seriesCategory', 'batches.allocations' => fn ($query) => $query->whereHas('stockOut', fn ($issues) => $issues->whereDate('stocked_out_at', '<=', $to->toDateString())), 'batches.allocations.stockOut', 'batches.countAdjustments', 'batches.returnPostings'])->orderBy('stock_number')->get();
        $rows = [];
        $reconciliation = [];
        $total = BigDecimal::of('0.00');
        foreach ($items as $item) {
            if ($item->created_at > $to && ! $item->batches->contains(fn ($batch): bool => $batch->received_at->toDateString() <= $to->toDateString())) {
                continue;
            }
            if ($item->quantity !== $item->batches->sum('quantity_remaining')) {
                $blockers[] = ['kind' => 'Stock reconciliation', 'reference' => $item->stock_number, 'status' => 'mismatch', 'reason' => 'Current catalog quantity differs from its batch balances. Investigate and use an approved correction; reports never overwrite stock.'];
            }
            $opening = 0;
            $closing = 0;
            $received = 0;
            $returned = 0;
            $issued = 0;
            $adjusted = 0;
            $value = BigDecimal::of('0.00');
            foreach ($item->batches as $batch) {
                if ($batch->received_at->toDateString() > $to->toDateString()) {
                    continue;
                }
                $before = $batch->received_at->toDateString() < $from->toDateString();
                $open = $before ? $batch->quantity_in : 0;
                $remaining = $batch->quantity_in;
                if (! $before) {
                    if ($batch->returnPostings->isNotEmpty()) {
                        $returned += $batch->quantity_in;
                    } elseif ($batch->countAdjustments->contains(fn ($entry): bool => $entry->quantity > 0)) {
                        $adjusted += $batch->quantity_in;
                    } else {
                        $received += $batch->quantity_in;
                    }
                }
                foreach ($batch->allocations as $allocation) {
                    $date = $allocation->stockOut->stocked_out_at->toDateString();
                    $remaining -= $allocation->quantity;
                    if ($date < $from->toDateString()) {
                        $open -= $allocation->quantity;
                    } else {
                        $issued += $allocation->quantity;
                    }
                }
                foreach ($batch->countAdjustments as $adjustment) {
                    if ($adjustment->quantity >= 0 || $adjustment->created_at > $to->copy()->timezone(config('app.timezone'))) {
                        continue;
                    }
                    $remaining += $adjustment->quantity;
                    if ($adjustment->created_at < $from->copy()->timezone(config('app.timezone'))) {
                        $open += $adjustment->quantity;
                    } else {
                        $adjusted += $adjustment->quantity;
                    }
                }
                if ($remaining < 0 || $open < 0) {
                    $blockers[] = ['kind' => 'Historical stock reconciliation', 'reference' => $item->stock_number, 'status' => 'negative', 'reason' => 'Issue or adjustment dates precede sufficient batch receipts. Correct the source evidence before signing this month.'];
                }
                $opening += $open;
                $closing += $remaining;
                $value = $value->plus(BigDecimal::of($batch->unit_cost)->multipliedBy($remaining));
            }
            $expected = $opening + $received + $returned - $issued + $adjusted;
            $reconciliation[] = ['stock_number' => $item->stock_number, 'opening' => $opening, 'receipts' => $received, 'returns' => $returned, 'issues' => $issued, 'adjustments' => $adjusted, 'closing' => $closing, 'difference' => $closing - $expected];
            if ($closing !== $expected) {
                $blockers[] = ['kind' => 'Period stock reconciliation', 'reference' => $item->stock_number, 'status' => 'mismatch', 'reason' => 'Opening plus movements does not equal closing stock.'];
            }
            if (($categoryId === null || $item->series_category_id === $categoryId) && ($includeZero || $closing !== 0)) {
                $rows[] = [$item->stock_number, $item->name, $item->seriesCategory->name ?? 'Unclassified', $item->unit_of_measure, $opening, $received, $returned, $issued, $adjusted, $closing, (string) $value];
                $total = $total->plus($value);
            }
        }

        return [$rows, $reconciliation, (string) $total];
    }
}
