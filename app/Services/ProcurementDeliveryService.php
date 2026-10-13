<?php

namespace App\Services;

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\ProcurementDelivery;
use App\Models\ProcurementDeliveryLine;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestLine;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementDeliveryService
{
    public function __construct(private InventoryStockGuard $guard, private InventoryStockService $stock, private ProcurementRequestWorkflow $procurement, private SupplyRequestWorkflow $supplies, private AuditLogger $audit) {}

    public function availableQuantity(ProcurementRequestLine $line): int
    {
        $committed = ProcurementDeliveryLine::query()->where('procurement_request_line_id', $line->id)->whereHas('delivery', fn ($query) => $query->whereIn('status', ['received', 'inspected', 'accepted']))->sum('quantity_committed');

        return max(0, $line->quantity - (int) $committed);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, User $actor): ProcurementDelivery
    {
        return DB::transaction(function () use ($data, $actor): ProcurementDelivery {
            $this->guard->lock();
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = ProcurementDelivery::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                if ($existing->created_by_user_id !== $actor->id || $existing->request_hash !== $hash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to another delivery.']);
                }

                return $existing;
            }
            $order = ProcurementRequest::query()->whereKey($data['procurement_request_id'])->lockForUpdate()->with('lines')->firstOrFail();
            if ($order->document_kind !== 'PR' || ! in_array($order->status, ['ordered', 'delivered'], true) || blank($order->purchase_order_no)) {
                throw ValidationException::withMessages(['procurement_request_id' => 'Select an active ordered purchase request with a recorded PO.']);
            }
            if ($order->deliveries()->doesntExist() && $order->lines->contains(fn ($line) => $line->quantity_received > 0)) {
                throw ValidationException::withMessages(['procurement_request_id' => 'Finish the existing legacy delivery before opening a new shipment workflow.']);
            }
            if ($order->deliveries()->where('delivery_reference', $data['delivery_reference'])->exists()) {
                throw ValidationException::withMessages(['delivery_reference' => 'This delivery reference is already recorded for the PO.']);
            }
            $lines = [];
            foreach ($data['lines'] as $input) {
                $line = $order->lines->firstWhere('id', (int) $input['line_id']);
                if ($line === null || $input['quantity_delivered'] > $this->availableQuantity($line)) {
                    throw ValidationException::withMessages(['lines' => 'Every line must belong to this PO and stay within its uncommitted ordered quantity.']);
                }
                $lines[] = ['procurement_request_line_id' => $line->id, 'item_name' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure, 'specifications' => $line->specifications, 'quantity_delivered' => $input['quantity_delivered'], 'quantity_committed' => $input['quantity_delivered'], 'unit_cost' => $input['unit_cost'], 'expiration_date' => $input['expiration_date'] ?? null];
            }
            $from = $order->status;
            $document = ProcurementDelivery::query()->create([
                ...collect($data)->only(['supplier_name', 'invoice_number', 'delivery_reference', 'receiving_department', 'noa_reference', 'ntp_reference', 'order_document_basis', 'notification_reference', 'packaging_findings', 'idempotency_key'])->all(),
                'procurement_request_id' => $order->id, 'purchase_order_no' => $order->purchase_order_no, 'document_no' => 'TMP-'.Str::uuid(),
                'received_at' => Carbon::createFromFormat('!Y-m-d\\TH:i', $data['received_at'], 'Asia/Manila')->timezone(config('app.timezone')),
                'created_by_user_id' => $actor->id, 'request_hash' => $hash]);
            $document->update(['document_no' => sprintf('DEL-%s-%06d', now('Asia/Manila')->format('Y'), $document->id)]);
            $document->lines()->createMany($lines);
            $order->update(['status' => 'delivered']);
            $this->record($document, $order, $actor, 'receive', null, $data['packaging_findings'], ['order_from_status' => $from, 'intake' => $data]);

            return $document;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $data */
    public function transition(ProcurementDelivery $document, array $data, User $actor): void
    {
        abort_unless($actor->can('manage-inventory'), 403);
        DB::transaction(function () use ($document, $data, $actor): void {
            $this->guard->lock();
            $order = ProcurementRequest::query()->whereKey($document->procurement_request_id)->lockForUpdate()->with(['lines.item', 'lines.assetCategory', 'lines.assetSubcategory', 'supplyRequest'])->firstOrFail();
            $document = ProcurementDelivery::query()->whereKey($document->id)->lockForUpdate()->with('lines')->firstOrFail();
            $action = $data['action'];
            $from = $document->status;
            if ($action === 'postpone') {
                $existing = $document->actions()->where('action', 'postpone')->where('metadata->notice_submission_key', $data['notice_submission_key'])->first();
                if ($existing !== null) {
                    if ($existing->actor_user_id !== $actor->id || data_get($existing->metadata, 'request_hash') !== hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))) {
                        throw ValidationException::withMessages(['notice_submission_key' => 'This submission key belongs to another postponement notice.']);
                    }

                    return;
                }
            }
            if (! in_array($action, $this->allowedActions($document, $order, $actor), true)) {
                throw ValidationException::withMessages(['action' => 'This decision is unavailable at this stage or requires an independent officer.']);
            }
            if ($action === 'postpone') {
                $data = [...$data, ...$this->postponementEvidence($document, $data)];
            } elseif ($action === 'inspect') {
                if ($document->actions()->where('action', 'postpone')->exists() && blank($data['resumption_reference'] ?? null)) {
                    throw ValidationException::withMessages(['resumption_reference' => 'Record confirmation of supplier availability and the restored inspection team before resuming a postponed inspection.']);
                }
                $data = [...$data, ...$this->evaluationEvidence($document, $data)];
                $this->inspect($document, $order, $data, $actor);
            } elseif ($action === 'accept') {
                $this->accept($document, $order, $actor);
            } elseif ($action === 'cancel') {
                $document->update(['status' => 'cancelled']);
            } elseif ($action === 'authorize_supplier_return') {
                $data = [...$data, ...$this->supplierReturnEvidence($document, $data)];
            } elseif ($action === 'record_supplier_return') {
                if ($data['returned_at'] < $document->received_at->timezone('Asia/Manila')->toDateString()) {
                    throw ValidationException::withMessages(['returned_at' => 'Supplier return cannot precede delivery receipt.']);
                }
                $authorization = $document->actions()->where('action', 'authorize_supplier_return')->sole();
                if (data_get($authorization->metadata, 'return_source_snapshot') !== $this->deliverySnapshot($document)) {
                    throw ValidationException::withMessages(['return_reference' => 'Delivery evidence changed after the signed notice. Review the original notice before supplier handover.']);
                }
                if ($data['returned_at'] < data_get($authorization->metadata, 'return_notice_date')) {
                    throw ValidationException::withMessages(['returned_at' => 'Physical handover cannot precede the signed return notice.']);
                }
                $document->update(['supplier_returned_at' => Carbon::parse($data['returned_at'], 'Asia/Manila')->timezone(config('app.timezone'))]);
            } else {
                $document->update(['endorsed_at' => now()]);
            }
            $this->record($document, $order, $actor, $action, $from, $data['remarks'], collect($data)->except(['action', 'remarks', 'attested'])->all());
        }, attempts: 3);
    }

    /** @return list<string> */
    public function allowedActions(ProcurementDelivery $document, ProcurementRequest $order, User $actor): array
    {
        if (! $actor->can('manage-inventory')) {
            return [];
        }
        $independent = $actor->id !== $document->created_by_user_id && $actor->id !== $order->created_by_user_id && $actor->id !== (int) ($order->preprocurement['encoded_by_user_id'] ?? 0);
        $actions = match ($document->status) {
            'received' => [...($independent ? ['inspect'] : []), 'postpone', 'cancel'],
            'inspected' => [...($independent && $document->inspected_by_user_id !== $actor->id ? ['accept'] : []), 'cancel'],
            default => [],
        };
        if ($document->supplier_return_no !== null && $document->supplier_returned_at === null && in_array($document->status, ['inspected', 'accepted', 'rejected'], true)) {
            $actions[] = $document->actions()->where('action', 'authorize_supplier_return')->exists() ? 'record_supplier_return' : 'authorize_supplier_return';
        }
        if ($document->status === 'accepted' && $document->endorsed_at === null) {
            $actions[] = 'endorse';
        }

        return $actions;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function postponementEvidence(ProcurementDelivery $document, array $data): array
    {
        $original = Carbon::parse($data['original_inspection_at'], 'Asia/Manila');
        $proposed = Carbon::parse($data['proposed_inspection_at'], 'Asia/Manila');
        if ($original->lt($document->received_at) || ! $proposed->gt($original) || $proposed->toDateString() < $data['notice_date']) {
            throw ValidationException::withMessages(['proposed_inspection_at' => 'The original schedule must follow receipt, and the proposed schedule must follow the original schedule and notice date.']);
        }
        $previous = $document->actions()->where('action', 'postpone')->latest('id')->first();
        if ($previous !== null && ($data['original_inspection_at'] !== data_get($previous->metadata, 'proposed_inspection_at') || $data['original_site'] !== data_get($previous->metadata, 'proposed_site'))) {
            throw ValidationException::withMessages(['original_inspection_at' => 'Use the latest recorded proposed date, time and venue as the original schedule for another postponement.']);
        }

        return ['notice_no' => sprintf('NOP-%s-%06d-%03d', now('Asia/Manila')->format('Y'), $document->id, $document->actions()->where('action', 'postpone')->count() + 1),
            'request_hash' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
            'chair' => $this->personSnapshot($data['chair_reference_id']), 'authority' => $this->personSnapshot($data['authority_reference_id'])];
    }

    /** @return array<string,mixed> */
    private function personSnapshot(int|string $id): array
    {
        $reference = HrisReference::query()->whereKey($id)->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->firstOrFail();

        return ['id' => $reference->id, 'name' => $reference->name, 'position' => data_get($reference->metadata, 'position.name'), 'office' => $reference->officeName()];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function evaluationEvidence(ProcurementDelivery $document, array $data): array
    {
        if ($data['evaluation_date'] < $document->received_at->timezone('Asia/Manila')->toDateString()) {
            throw ValidationException::withMessages(['evaluation_date' => 'Evaluation cannot precede physical receipt.']);
        }
        $chair = $this->personSnapshot($data['inspection_chair_reference_id']);
        $vice = $this->personSnapshot($data['inspection_vice_reference_id']);
        $endUser = $this->personSnapshot($data['inspection_end_user_reference_id']);

        return ['evaluation_no' => sprintf('IER-%s-%06d', now('Asia/Manila')->format('Y'), $document->id),
            'source_snapshot' => $this->deliverySnapshot($document),
            'evaluator' => $this->personSnapshot($data['evaluator_reference_id']), 'evaluation_chair' => $chair,
            'inspection_vice' => $vice, 'inspection_end_user' => $endUser,
            'chairperson_name' => $chair['name'], 'vice_chairperson_name' => $vice['name'], 'end_user_name' => $endUser['name']];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function supplierReturnEvidence(ProcurementDelivery $document, array $data): array
    {
        $inspectionDate = data_get($document->inspection, 'evaluation_date', $document->received_at->timezone('Asia/Manila')->toDateString());
        if ($data['return_notice_date'] < $inspectionDate) {
            throw ValidationException::withMessages(['return_notice_date' => 'The signed return notice cannot precede inspection.']);
        }

        return ['return_notice_no' => $document->supplier_return_no,
            'return_source_snapshot' => $this->deliverySnapshot($document),
            'return_preparer' => $this->personSnapshot($data['return_preparer_reference_id']),
            'return_end_user' => $this->personSnapshot($data['return_end_user_reference_id']),
            'return_procurement_head' => $this->personSnapshot($data['return_procurement_head_reference_id']),
            'return_chair' => $this->personSnapshot($data['return_chair_reference_id'])];
    }

    /** @return array<string,mixed> */
    private function deliverySnapshot(ProcurementDelivery $document): array
    {
        return ['supplier_name' => $document->supplier_name, 'purchase_order_no' => $document->purchase_order_no,
            'invoice_number' => $document->invoice_number, 'delivery_reference' => $document->delivery_reference,
            'document_no' => $document->document_no,
            'lines' => $document->lines->map(fn (ProcurementDeliveryLine $line): array => $line->only(['id', 'item_name', 'unit_of_measure', 'specifications', 'quantity_delivered', 'quantity_accepted', 'findings']))->all()];
    }

    /** @param array<string,mixed> $data */
    private function inspect(ProcurementDelivery $document, ProcurementRequest $order, array $data, User $actor): void
    {
        if (! isset($data['lines']) || ! is_array($data['lines'])) {
            throw ValidationException::withMessages(['lines' => 'Inspect each delivered line.']);
        }
        $input = collect($data['lines']);
        if ($input->pluck('line_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $document->lines->pluck('id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['lines' => 'Inspect every line exactly once on this delivery.']);
        }
        $any = false;
        $rejected = false;
        foreach ($document->lines as $line) {
            $finding = $input->firstWhere('line_id', $line->id);
            $accepted = (int) $finding['quantity_accepted'];
            if ($accepted < $line->quantity_delivered && empty($finding['nonconformities'])) {
                throw ValidationException::withMessages(['lines' => 'Select the non-conformance checklist categories for each rejected quantity.']);
            }
            if ($accepted > $line->quantity_delivered || ($accepted > 0 && $finding['condition'] !== 'conforming')) {
                throw ValidationException::withMessages(['lines' => 'Accepted quantities must conform to specifications and cannot exceed physical delivery.']);
            }
            if ($accepted > 0 && $line->expiration_date !== null && $line->expiration_date->toDateString() < now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['lines' => 'Expired materials cannot enter usable stock.']);
            }
            $orderLine = $order->lines->firstWhere('id', $line->procurement_request_line_id);
            if ($accepted > 0) {
                if (blank($finding['storage_location'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => 'Record the storage location or direct-use destination for accepted materials.']);
                }
                if (in_array($finding['disposition'], ['calibration', 'direct_use'], true) && blank($finding['disposition_reference'] ?? null)) {
                    throw ValidationException::withMessages(['lines' => 'Record the passed calibration report or signed end-user endorsement before acceptance.']);
                }
                if ($finding['disposition'] === 'direct_use' && $orderLine->supply_request_line_id !== null) {
                    throw ValidationException::withMessages(['lines' => 'A linked stock replenishment must enter the warehouse before release through its supply request.']);
                }
            }
            $line->update(['quantity_accepted' => $accepted, 'quantity_committed' => $accepted, 'condition' => $finding['condition'], 'disposition' => $finding['disposition'], 'storage_location' => $finding['storage_location'] ?? null, 'disposition_reference' => $finding['disposition_reference'] ?? null, 'findings' => $finding['findings']]);
            $any = $any || $accepted > 0;
            $rejected = $rejected || $accepted < $line->quantity_delivered;
        }
        if ($rejected && blank($data['supplier_notification_reference'] ?? null)) {
            throw ValidationException::withMessages(['supplier_notification_reference' => 'Record the notification to the supplier or Procurement Unit for discrepancies.']);
        }
        $document->update(['status' => $any ? 'inspected' : 'rejected', 'inspected_by_user_id' => $actor->id,
            'inspection_acceptance_no' => $any ? sprintf('IAR-%s-%06d', now('Asia/Manila')->format('Y'), $document->id) : null,
            'supplier_return_no' => $rejected ? sprintf('RTS-%s-%06d', now('Asia/Manila')->format('Y'), $document->id) : null,
            'inspection' => collect($data)->only(['inspection_reference', 'chairperson_name', 'vice_chairperson_name', 'end_user_name', 'coa_witness_name', 'supplier_notification_reference', 'resumption_reference',
                'evaluation_no', 'evaluation_date', 'order_reference_date', 'invoice_date', 'delivery_receipt_date', 'inspection_site', 'evaluation_findings', 'evaluation_comments',
                'evaluator', 'evaluation_chair', 'inspection_vice', 'inspection_end_user', 'evaluator_signature_reference', 'evaluation_chair_signature_reference'])->all()]);
    }

    private function accept(ProcurementDelivery $document, ProcurementRequest $order, User $actor): void
    {
        foreach ($document->lines as $receipt) {
            $quantity = $receipt->quantity_accepted ?? 0;
            if ($quantity === 0) {
                continue;
            }
            if ($receipt->expiration_date !== null && $receipt->expiration_date->toDateString() < now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['action' => 'An accepted batch has expired since inspection. Cancel and inspect a corrected shipment.']);
            }
            $line = $order->lines->firstWhere('id', $receipt->procurement_request_line_id);
            $posted = [];
            if ($line->item_kind === 'property') {
                $this->procurement->registerPropertyLine($order, $line, $document->inspection_acceptance_no, $actor, $quantity, $receipt->unit_cost, $document->received_at->timezone('Asia/Manila')->toDateString(), $receipt->storage_location);
                $posted['asset_ids'] = $line->assets()->where('inspection_acceptance_report_no', $document->inspection_acceptance_no)->pluck('inventory_asset_id')->all();
            } elseif ($receipt->disposition !== 'direct_use') {
                $this->guard->assertMovementAllowed();
                $item = $line->item;
                if ($item === null && $line->inventory_item_id !== null) {
                    throw ValidationException::withMessages(['action' => 'Restore the archived catalog item before accepting this stock.']);
                }
                if ($item === null) {
                    if ($line->series_category_id === null) {
                        throw ValidationException::withMessages(['action' => 'Assign the new item category before stock acceptance.']);
                    }
                    $item = InventoryItem::query()->create(['series_category_id' => $line->series_category_id, 'name' => $receipt->item_name, 'stock_number' => 'NEW-'.Str::upper(Str::random(8)), 'unit_of_measure' => $line->unit_of_measure, 'description' => $line->specifications, 'reorder_point' => 0, 'status' => 'active']);
                    $line->update(['inventory_item_id' => $item->getKey()]);
                }
                if ($item->status !== 'active') {
                    throw ValidationException::withMessages(['action' => 'Activate the catalog item before stock acceptance.']);
                }
                $item = $this->stock->stockIn($item, ['quantity' => $quantity, 'unit_cost' => $receipt->unit_cost, 'received_at' => now('Asia/Manila')->toDateString(), 'expiration_date' => $receipt->expiration_date?->toDateString(), 'source' => $document->supplier_name, 'reference_no' => $document->inspection_acceptance_no, 'batch_notes' => $document->document_no.' / '.$document->delivery_reference.' / '.$receipt->storage_location]);
                $posted['inventory_item_batch_id'] = $item->batches->sortByDesc('inventory_item_batch_id')->first()->getKey();
                if ($line->supply_request_line_id !== null) {
                    $line->supplyRequestLine()->update(['inventory_item_id' => $item->getKey(), 'is_new_item' => false, 'item_name' => $item->name, 'unit_of_measure' => $item->unit_of_measure]);
                }
            }
            $receipt->update($posted);
            $line->update(['quantity_received' => $line->quantity_received + $quantity, 'actual_unit_cost' => $receipt->unit_cost, 'received_at' => $document->received_at->timezone('Asia/Manila')->toDateString(), 'delivery_reference' => $document->delivery_reference]);
        }
        $document->update(['status' => 'accepted', 'accepted_by_user_id' => $actor->id, 'accepted_at' => now(), 'receiving_report_no' => sprintf('RR-%s-%06d', now('Asia/Manila')->format('Y'), $document->id)]);
        $order->load('lines');
        $complete = $order->lines->every(fn ($line): bool => $line->quantity_received >= $line->quantity);
        $order->update(['status' => $complete ? 'accepted' : 'delivered', 'completed_at' => $complete ? now() : null, 'inspection_acceptance_no' => $document->inspection_acceptance_no]);
        if ($order->supplyRequest !== null) {
            $this->supplies->reassessAfterProcurement($order->supplyRequest, $actor, $order->pr_no);
        }
    }

    /** @param array<string,mixed> $metadata */
    private function record(ProcurementDelivery $document, ProcurementRequest $order, User $actor, string $action, ?string $from, string $remarks, array $metadata): void
    {
        $document->actions()->create(['actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'from_status' => $from, 'to_status' => $document->status, 'remarks' => $remarks, 'metadata' => $metadata]);
        $order->actions()->create(['actor_user_id' => $actor->id, 'action' => 'delivery_'.$action, 'from_status' => $order->status, 'to_status' => $order->status, 'remarks' => $remarks, 'attestation' => 'Delivery evidence and quantities confirmed.', 'metadata' => ['delivery_id' => $document->id, 'delivery_no' => $document->document_no, 'actor_name' => $actor->name, ...$metadata]]);
        $this->audit->record('procurement.delivery.'.$action, $document->document_no.': '.$action, $document, newValues: $metadata, actor: $actor);
    }
}
