<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementRequestWorkflow
{
    public function __construct(private InventoryStockService $stockService) {}

    public function transition(ProcurementRequest $request, User $actor, array $data): ProcurementRequest
    {
        return DB::transaction(function () use ($request, $actor, $data): ProcurementRequest {
            $request = ProcurementRequest::query()->whereKey($request->getKey())->lockForUpdate()->with('lines.item')->firstOrFail();
            if ($request->created_by_user_id === $actor->id && in_array($data['action'], ['budget_review', 'approve', 'accept'], true)) {
                throw ValidationException::withMessages(['action' => 'The preparer cannot review, approve, or accept their own procurement request.']);
            }
            $from = $request->status;
            $action = $data['action'];
            $to = match ($action) {
                'submit' => $this->allowed($from, ['draft'], 'for_budget_review'), 'budget_review' => $this->budgetReview($request, $data),
                'approve' => $this->allowed($from, ['for_approval'], 'approved'), 'forward' => $this->allowed($from, ['approved'], 'forwarded_to_procurement'),
                'order' => $this->order($request, $data), 'record_delivery' => $this->delivery($request, $data), 'accept' => $this->accept($request, $data),
                'reject' => $this->allowed($from, ['for_budget_review', 'for_approval', 'approved'], 'rejected'), 'cancel' => $this->allowed($from, ['draft', 'for_budget_review', 'for_approval', 'approved', 'forwarded_to_procurement', 'ordered'], 'cancelled'),
                default => throw ValidationException::withMessages(['action' => 'Unsupported workflow action.']),
            };
            $request->update(['status' => $to, 'completed_at' => in_array($to, ['accepted', 'rejected', 'cancelled'], true) ? now() : null]);
            $request->actions()->create(['actor_user_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'remarks' => $data['remarks'] ?? null, 'attestation' => 'I confirm this decision and its supporting records are accurate.', 'metadata' => collect($data)->except(['attested', 'remarks', 'action'])->all()]);

            return $request->load(['lines.item', 'actions.actor']);
        });
    }

    private function budgetReview(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['for_budget_review'], 'for_approval');
        if (blank($request->funding_source) || blank($request->ppmp_reference) || blank($request->app_reference)) {
            throw ValidationException::withMessages(['action' => 'Funding source, PPMP reference, and APP reference are required before budget clearance.']);
        }

        return 'for_approval';
    }

    private function order(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['forwarded_to_procurement'], 'ordered');
        if (blank($data['purchase_order_no'] ?? null) || blank($data['procurement_mode'] ?? null)) {
            throw ValidationException::withMessages(['purchase_order_no' => 'Purchase order number and procurement mode are required.']);
        }
        $request->update(['purchase_order_no' => $data['purchase_order_no'], 'procurement_mode' => $data['procurement_mode']]);

        return 'ordered';
    }

    private function delivery(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['ordered', 'delivered'], 'delivered');
        if (blank($data['delivery_reference'] ?? null) || blank($data['received_at'] ?? null)) {
            throw ValidationException::withMessages(['delivery_reference' => 'Delivery reference and received date are required.']);
        }
        foreach ($request->lines as $line) {
            $line->update(['quantity_received' => $line->quantity, 'actual_unit_cost' => $data['actual_unit_cost'] ?? $line->estimated_unit_cost, 'received_at' => $data['received_at'], 'delivery_reference' => $data['delivery_reference']]);
        }

        return 'delivered';
    }

    private function accept(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['delivered'], 'accepted');
        if (blank($data['inspection_acceptance_no'] ?? null)) {
            throw ValidationException::withMessages(['inspection_acceptance_no' => 'Inspection and Acceptance Report number is required.']);
        }
        foreach ($request->lines as $line) {
            $item = $line->item;
            if ($item === null) {
                if ($line->series_category_id === null) {
                    throw ValidationException::withMessages(['action' => "A category is required before accepting new item {$line->item_name}."]);
                }
                $item = InventoryItem::query()->create(['series_category_id' => $line->series_category_id, 'name' => $line->item_name, 'stock_number' => 'NEW-'.Str::upper(Str::random(8)), 'unit_of_measure' => $line->unit_of_measure, 'description' => $line->specifications, 'reorder_point' => 0, 'status' => 'active']);
                $line->update(['inventory_item_id' => $item->getKey()]);
            }
            $this->stockService->stockIn($item, ['quantity' => $line->quantity_received, 'unit_cost' => $line->actual_unit_cost, 'received_at' => $line->received_at?->toDateString(), 'source' => 'Procurement', 'reference_no' => $line->delivery_reference, 'batch_notes' => 'Accepted under '.$data['inspection_acceptance_no']]);
        }
        $request->update(['inspection_acceptance_no' => $data['inspection_acceptance_no']]);

        return 'accepted';
    }

    private function allowed(string $current, array $allowed, string $next): string
    {
        if (! in_array($current, $allowed, true)) {
            throw ValidationException::withMessages(['action' => "This action is not allowed while the request is {$current}."]);
        }

        return $next;
    }
}
