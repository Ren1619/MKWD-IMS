<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProcurementRequestIndexRequest;
use App\Http\Requests\Inventory\StoreProcurementRequestRequest;
use App\Http\Requests\Inventory\TransitionProcurementRequestRequest;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Models\ProcurementRequest;
use App\Services\ProcurementRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementRequestController extends Controller
{
    public function __construct(private ProcurementRequestWorkflow $workflow) {}

    public function index(ProcurementRequestIndexRequest $request): Response
    {
        $filters = $request->validated();
        $procurementRequests = ProcurementRequest::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('pr_no', 'like', "%{$search}%")
                        ->orWhere('purpose', 'like', "%{$search}%")
                        ->orWhere('purchase_order_no', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lines', fn ($query) => $query->where('item_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['queue'] ?? null) === 'needs_action', fn ($query) => $query->whereNotIn('status', ['accepted', 'rejected', 'cancelled']))
            ->when(($filters['queue'] ?? null) === 'completed', fn ($query) => $query->whereIn('status', ['accepted', 'rejected', 'cancelled']))
            ->with(['lines.item', 'actions.actor:id,name', 'creator:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Procurement/Index', [
            'procurementRequests' => $procurementRequests,
            'items' => InventoryItem::query()->where('status', 'active')->orderBy('name')->get(['inventory_item_id', 'name', 'unit_of_measure', 'quantity', 'reorder_point', 'reorder_quantity']),
            'seriesCategories' => InventorySeriesCategory::query()->active()->orderBy('name')->get(['inv_series_cat_id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function store(StoreProcurementRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $record = ProcurementRequest::query()->create([...$request->safe()->except('lines'), 'pr_no' => 'TMP-'.Str::uuid(), 'created_by_user_id' => $request->user()->id, 'status' => 'draft']);
            $record->update(['pr_no' => sprintf('PR-%s-%06d', now()->format('Y'), $record->id)]);
            $record->lines()->createMany($request->validated('lines'));
            $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => 'prepare', 'to_status' => 'draft', 'attestation' => 'I certify this procurement request is complete and supported.']);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procurement request prepared.']);

        return back();
    }

    public function transition(TransitionProcurementRequestRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->workflow->transition($procurementRequest, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procurement workflow updated.']);

        return back();
    }
}
