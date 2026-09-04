<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplyRequestRequest;
use App\Http\Requests\Inventory\SupplyRequestIndexRequest;
use App\Http\Requests\Inventory\TransitionSupplyRequestRequest;
use App\Models\InventoryItem;
use App\Models\SupplyRequest;
use App\Services\SupplyRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupplyRequestController extends Controller
{
    public function __construct(private SupplyRequestWorkflow $workflow) {}

    public function index(SupplyRequestIndexRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->validated();
        $items = InventoryItem::query()
            ->where('status', 'active')
            ->with('batches:inventory_item_batch_id,inventory_item_id,quantity_remaining,unit_cost')
            ->orderBy('name')
            ->get(['inventory_item_id', 'name', 'stock_number', 'unit_of_measure', 'quantity', 'reorder_point'])
            ->each(function (InventoryItem $item): void {
                $remainingQuantity = $item->batches->sum('quantity_remaining');
                $inventoryValue = $item->batches->sum(
                    fn ($batch): float => $batch->quantity_remaining * (float) $batch->unit_cost,
                );

                $item->setAttribute(
                    'weighted_average_unit_cost',
                    $remainingQuantity > 0 ? number_format($inventoryValue / $remainingQuantity, 2, '.', '') : null,
                );
                $item->unsetRelation('batches');
            });
        $requests = SupplyRequest::query()
            ->when(! $user->canManageInventory(), fn ($query) => $query->where('requester_user_id', $user->id))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('ris_no', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('office_name', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhereHas('lines', fn ($query) => $query->where('item_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['queue'] ?? null) === 'needs_action', fn ($query) => $query->whereNotIn('status', ['released', 'rejected', 'cancelled']))
            ->when(($filters['queue'] ?? null) === 'completed', fn ($query) => $query->whereIn('status', ['released', 'rejected', 'cancelled']))
            ->with(['lines.item:inventory_item_id,name,quantity,unit_of_measure', 'actions.actor:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Requests/Index', [
            'requests' => $requests,
            'items' => $items,
            'canManage' => $user->canManageInventory(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreSupplyRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();
            $record = SupplyRequest::query()->create(['ris_no' => 'TMP-'.Str::uuid(), 'requester_user_id' => $user->id, 'requester_reference_id' => $user->hris_reference_id, 'requester_name' => $user->name, 'office_name' => $request->string('office_name')->toString() ?: null, 'responsibility_center_code' => $request->string('responsibility_center_code')->toString() ?: null, 'purpose' => $request->string('purpose'), 'date_needed' => $request->date('date_needed'), 'status' => 'submitted', 'submitted_at' => now()]);
            $record->update(['ris_no' => sprintf('RIS-%s-%06d', now()->format('Y'), $record->id)]);
            foreach ($request->validated('lines') as $line) {
                $item = filled($line['inventory_item_id'] ?? null) ? InventoryItem::findOrFail($line['inventory_item_id']) : null;
                $record->lines()->create(['inventory_item_id' => $item?->getKey(), 'is_new_item' => (bool) $line['is_new_item'], 'item_name' => $item?->name ?? $line['item_name'], 'specifications' => $line['specifications'] ?? null, 'unit_of_measure' => $item?->unit_of_measure ?? $line['unit_of_measure'], 'quantity_requested' => $line['quantity'], 'estimated_unit_cost' => $line['estimated_unit_cost'] ?? null, 'justification' => $line['justification'] ?? null]);
            }

            $record->actions()->create(['actor_user_id' => $user->id, 'action' => 'submit', 'to_status' => 'submitted', 'attestation' => 'I certify this request is necessary for official use.']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supply request submitted.']);

        return back();
    }

    public function transition(TransitionSupplyRequestRequest $request, SupplyRequest $supplyRequest): RedirectResponse
    {
        $this->workflow->transition($supplyRequest, $request->user(), $request->string('action')->toString(), $request->string('remarks')->toString() ?: null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request workflow updated.']);

        return back();
    }
}
