<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementRequestWorkflow
{
    public function __construct(
        private InventoryStockService $stockService,
        private SupplyRequestWorkflow $supplyRequestWorkflow,
        private InventoryAssetLifecycleService $assetLifecycleService,
        private PreprocurementWorkflow $preprocurementWorkflow,
    ) {}

    /** @param array<string, mixed> $data */
    public function transition(ProcurementRequest $request, User $actor, array $data): ProcurementRequest
    {
        return DB::transaction(function () use ($request, $actor, $data): ProcurementRequest {
            app(InventoryStockGuard::class)->lock();
            $request = ProcurementRequest::query()->whereKey($request->getKey())->lockForUpdate()->with(['lines.item', 'lines.assetCategory', 'lines.assetSubcategory'])->firstOrFail();
            if ($request->created_by_user_id === $actor->id && in_array($data['action'], ['budget_review', 'approve', 'accept'], true)) {
                throw ValidationException::withMessages(['action' => 'The preparer cannot review, approve, or accept their own procurement request.']);
            }
            if ((int) ($request->preprocurement['encoded_by_user_id'] ?? 0) === $actor->id && in_array($data['action'], ['approve', 'accept'], true)) {
                throw ValidationException::withMessages(['action' => 'The encoder cannot approve or accept their own prepared document.']);
            }
            $from = $request->status;
            $action = $data['action'];
            if (in_array($action, ['record_delivery', 'accept', 'cancel'], true) && $request->deliveries()->exists()) {
                throw ValidationException::withMessages(['action' => 'Manage recorded shipments through Delivery Inspection so quantities and inspection evidence remain intact.']);
            }
            if ($request->preprocurement !== null && in_array($action, ['approve', 'reject'], true) && $from === 'for_approval' && (int) ($request->preprocurement['gm_user_id'] ?? 0) !== $actor->id) {
                throw ValidationException::withMessages(['action' => 'Only the assigned GM approver can make this decision.']);
            }
            if ($request->document_kind === 'JO' && in_array($action, ['order', 'record_delivery', 'accept'], true)) {
                throw ValidationException::withMessages(['action' => 'Job orders end here at procurement endorsement; stock receipt actions apply to purchase requisitions.']);
            }
            if ($request->preprocurement !== null && $action === 'reject' && $from !== 'for_approval') {
                throw ValidationException::withMessages(['action' => 'Return incomplete requisitions for compliance, or cancel them with a reason.']);
            }
            if ($request->preprocurement !== null && in_array($action, ['cancel', 'reject'], true) && blank($data['remarks'] ?? null)) {
                throw ValidationException::withMessages(['remarks' => 'Record the reason for cancellation or disapproval.']);
            }
            $to = match ($action) {
                'submit_intake', 'return_for_compliance', 'verify_documents', 'prepare_document', 'sign' => $this->preprocurementWorkflow->transition($request, $actor, $data),
                'submit' => $this->allowed($from, ['draft'], 'for_budget_review'), 'budget_review' => $this->budgetReview($request, $data),
                'approve' => $this->allowed($from, ['for_approval'], 'approved'), 'forward' => $this->allowed($from, ['approved'], $request->document_kind === 'JO' ? 'endorsed' : 'forwarded_to_procurement'),
                'order' => $this->order($request, $data), 'record_delivery' => $this->delivery($request, $data), 'accept' => $this->accept($request, $data, $actor),
                'reject' => $this->allowed($from, ['for_budget_review', 'for_approval', 'approved'], $request->preprocurement !== null ? 'cancelled' : 'rejected'), 'cancel' => $this->allowed($from, ['intake', 'for_document_review', 'returned_for_compliance', 'ready_for_preparation', 'for_signatures', 'draft', 'for_budget_review', 'for_approval', 'approved', 'forwarded_to_procurement', 'ordered'], 'cancelled'),
                default => throw ValidationException::withMessages(['action' => 'Unsupported workflow action.']),
            };
            if ($request->preprocurement !== null && in_array($action, ['approve', 'reject', 'forward'], true)) {
                $details = $request->preprocurement;
                $details[$action] = ['user_id' => $actor->id, 'name' => $actor->name, 'at' => now()->toIso8601String(), 'recipient' => $data['endorsement_recipient'] ?? null];
                $request->update(['preprocurement' => $details]);
            }
            $request->update(['status' => $to, 'completed_at' => in_array($to, ['accepted', 'rejected', 'cancelled', 'endorsed'], true) ? now() : null]);
            $request->actions()->create(['actor_user_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'remarks' => $data['remarks'] ?? null, 'attestation' => 'I confirm this decision and its supporting records are accurate.', 'metadata' => collect($data)->except(['attested', 'remarks', 'action'])->all()]);

            return $request->load(['lines.item', 'lines.assetCategory', 'lines.assetSubcategory', 'lines.assets', 'actions.actor']);
        });
    }

    /** @param array<string, mixed> $data */
    private function budgetReview(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['for_budget_review'], 'for_approval');
        $request->update(collect($data)->only([
            'funding_source',
            'responsibility_center_code',
            'ppmp_reference',
            'app_reference',
        ])->filter(fn (mixed $value): bool => filled($value))->all());

        foreach ($data['line_categories'] ?? [] as $lineCategory) {
            $line = $request->lines->firstWhere('id', (int) $lineCategory['line_id']);

            if ($line === null) {
                throw ValidationException::withMessages([
                    'line_categories' => 'A submitted line does not belong to this procurement request.',
                ]);
            }

            if ($line->item_kind !== 'consumable') {
                throw ValidationException::withMessages([
                    'line_categories' => 'Inventory categories may only be assigned to consumable lines.',
                ]);
            }

            $line->update(['series_category_id' => $lineCategory['series_category_id']]);
        }

        $request->load('lines');
        if (blank($request->funding_source) || blank($request->ppmp_reference) || blank($request->app_reference)) {
            throw ValidationException::withMessages(['action' => 'Funding source, PPMP reference, and APP reference are required before budget clearance.']);
        }
        if ($request->lines->contains(fn ($line): bool => $line->item_kind === 'consumable' && $line->inventory_item_id === null && $line->series_category_id === null)) {
            throw ValidationException::withMessages(['line_categories' => 'Assign an inventory category to every new catalog item before budget clearance.']);
        }

        return 'for_approval';
    }

    /** @param array<string, mixed> $data */
    private function order(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['forwarded_to_procurement'], 'ordered');
        if (blank($data['purchase_order_no'] ?? null) || blank($data['procurement_mode'] ?? null)) {
            throw ValidationException::withMessages(['purchase_order_no' => 'Purchase order number and procurement mode are required.']);
        }
        $request->update(['purchase_order_no' => $data['purchase_order_no'], 'procurement_mode' => $data['procurement_mode']]);

        return 'ordered';
    }

    /** @param array<string, mixed> $data */
    private function delivery(ProcurementRequest $request, array $data): string
    {
        $this->allowed($request->status, ['ordered', 'delivered'], 'delivered');
        if (blank($data['delivery_reference'] ?? null) || blank($data['received_at'] ?? null)) {
            throw ValidationException::withMessages(['delivery_reference' => 'Delivery reference and received date are required.']);
        }
        $receipts = collect(is_array($data['line_receipts'] ?? null) ? $data['line_receipts'] : []);

        if ($receipts->isNotEmpty()) {
            $expectedLineIds = $request->lines->pluck('id')->sort()->values();
            $receivedLineIds = $receipts->pluck('line_id')->map(fn (mixed $id): int => (int) $id)->sort()->values();

            if ($receivedLineIds->duplicates()->isNotEmpty() || $receivedLineIds->all() !== $expectedLineIds->all()) {
                throw ValidationException::withMessages(['line_receipts' => 'Record the delivered quantity and actual unit cost for every procurement line.']);
            }

            foreach ($receipts as $receipt) {
                $line = $request->lines->firstWhere('id', (int) $receipt['line_id']);

                if ((int) $receipt['quantity_received'] !== $line->quantity) {
                    throw ValidationException::withMessages(['line_receipts' => "The current workflow requires a complete delivery for {$line->item_name}."]);
                }

                $line->update([
                    'quantity_received' => $receipt['quantity_received'],
                    'actual_unit_cost' => $receipt['actual_unit_cost'],
                    'received_at' => $data['received_at'],
                    'delivery_reference' => $data['delivery_reference'],
                ]);
            }
        } else {
            foreach ($request->lines as $line) {
                $actualUnitCost = $data['actual_unit_cost'] ?? $line->estimated_unit_cost;

                if ((float) $actualUnitCost <= 0) {
                    throw ValidationException::withMessages(['actual_unit_cost' => 'Actual unit cost must be greater than zero.']);
                }

                $line->update(['quantity_received' => $line->quantity, 'actual_unit_cost' => $actualUnitCost, 'received_at' => $data['received_at'], 'delivery_reference' => $data['delivery_reference']]);
            }
        }

        return 'delivered';
    }

    /** @param array<string, mixed> $data */
    private function accept(ProcurementRequest $request, array $data, User $actor): string
    {
        $this->allowed($request->status, ['delivered'], 'accepted');
        if (blank($data['inspection_acceptance_no'] ?? null)) {
            throw ValidationException::withMessages(['inspection_acceptance_no' => 'Inspection and Acceptance Report number is required.']);
        }
        foreach ($request->lines as $line) {
            if ($line->item_kind === 'property') {
                $this->registerPropertyLine($request, $line, $data['inspection_acceptance_no'], $actor);

                continue;
            }

            $item = $line->item;
            if ($item === null) {
                if ($line->series_category_id === null) {
                    throw ValidationException::withMessages(['action' => "A category is required before accepting new item {$line->item_name}."]);
                }
                $item = InventoryItem::query()->create(['series_category_id' => $line->series_category_id, 'name' => $line->item_name, 'stock_number' => 'NEW-'.Str::upper(Str::random(8)), 'unit_of_measure' => $line->unit_of_measure, 'description' => $line->specifications, 'reorder_point' => 0, 'status' => 'active']);
                $line->update(['inventory_item_id' => $item->getKey()]);
            }
            if ($line->supply_request_line_id !== null) {
                $line->supplyRequestLine()->update([
                    'inventory_item_id' => $item->getKey(),
                    'is_new_item' => false,
                    'item_name' => $item->name,
                    'unit_of_measure' => $item->unit_of_measure,
                ]);
            }
            $this->stockService->stockIn($item, ['quantity' => $line->quantity_received, 'unit_cost' => $line->actual_unit_cost, 'received_at' => $line->received_at?->toDateString(), 'source' => 'Procurement', 'reference_no' => $line->delivery_reference, 'batch_notes' => 'Accepted under '.$data['inspection_acceptance_no']]);
        }
        $request->update(['inspection_acceptance_no' => $data['inspection_acceptance_no']]);

        if ($request->supplyRequest !== null) {
            $this->supplyRequestWorkflow->reassessAfterProcurement(
                $request->supplyRequest,
                $actor,
                $request->pr_no,
            );
        }

        return 'accepted';
    }

    public function registerPropertyLine(
        ProcurementRequest $request,
        ProcurementRequestLine $line,
        string $inspectionAcceptanceNo,
        User $actor,
        ?int $acceptedQuantity = null,
        ?string $unitCost = null,
        ?string $receivedDate = null,
        ?string $location = null,
    ): void {
        if ($line->asset_category_id === null || $line->asset_subcategory_id === null) {
            throw ValidationException::withMessages(['action' => "Property category and subcategory are required before accepting {$line->item_name}."]);
        }

        if ($acceptedQuantity === null && ($line->quantity_received !== $line->quantity || $line->actual_unit_cost === null || $line->received_at === null)) {
            throw ValidationException::withMessages(['action' => "Complete the delivery details for {$line->item_name} before acceptance."]);
        }

        if ($acceptedQuantity === null && $line->assets()->exists()) {
            throw ValidationException::withMessages(['action' => "Property records have already been created for {$line->item_name}."]);
        }

        $quantity = $acceptedQuantity ?? $line->quantity_received;
        $cost = $unitCost ?? $line->actual_unit_cost;
        $date = $receivedDate ?? $line->received_at->toDateString();
        $offset = $line->assets()->count();
        for ($sequence = $offset + 1; $sequence <= $offset + $quantity; $sequence++) {
            $this->assetLifecycleService->register([
                'procurement_request_line_id' => $line->getKey(),
                'category_id' => $line->asset_category_id,
                'subcategory_id' => $line->asset_subcategory_id,
                'property_number' => sprintf('%s-L%d-%03d', $request->pr_no, $line->getKey(), $sequence),
                'name' => $line->item_name,
                'unit_of_measure' => $line->unit_of_measure,
                'description' => $line->specifications,
                'specifications' => $line->specifications,
                'location' => $location ?? 'Property and Supply Unit',
                'acquisition_date' => $date,
                'acquisition_method' => 'purchase',
                'purchase_order_no' => $request->purchase_order_no,
                'inspection_acceptance_report_no' => $inspectionAcceptanceNo,
                'delivery_date' => $date,
                'inspection_date' => today()->toDateString(),
                'acceptance_date' => today()->toDateString(),
                'available_for_use_date' => today()->toDateString(),
                'acquisition_cost' => $cost,
                'directly_attributable_costs' => 0,
                'depreciation_useful_life_months' => $line->assetCategory->default_useful_life_months ?? 60,
                'lifecycle_status' => 'active',
                'condition_status' => 'good',
            ], $actor);
        }
    }

    /** @param list<string> $allowed */
    private function allowed(string $current, array $allowed, string $next): string
    {
        if (! in_array($current, $allowed, true)) {
            throw ValidationException::withMessages(['action' => "This action is not allowed while the request is {$current}."]);
        }

        return $next;
    }
}
