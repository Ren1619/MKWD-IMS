<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryWarehouseIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryBatchPlacementRequest;
use App\Models\InventoryItemBatch;
use App\Services\InventoryWarehouseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryWarehouseController extends Controller
{
    public function index(InventoryWarehouseIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Warehouse/Index', [
            'batches' => InventoryItemBatch::query()->with(['item' => fn ($query) => $query->withTrashed(), 'latestPlacement'])
                ->when(! $request->boolean('include_depleted'), fn ($query) => $query->where('quantity_remaining', '>', 0))
                ->when($request->filled('search'), fn ($query) => $query->where(function ($nested) use ($request): void {
                    $search = '%'.$request->validated('search').'%';
                    $nested->where('reference_no', 'like', $search)->orWhere('source', 'like', $search)
                        ->orWhereHas('item', fn ($items) => $items->withoutGlobalScope(SoftDeletingScope::class)->where(fn ($match) => $match->where('name', 'like', $search)->orWhere('stock_number', 'like', $search)));
                }))
                ->when($request->filled('warehouse'), fn ($query) => $query->whereHas('latestPlacement', fn ($placement) => $placement->where('warehouse', $request->validated('warehouse'))))
                ->when($request->validated('placement') === 'unplaced', fn ($query) => $query->whereDoesntHave('latestPlacement'))
                ->when($request->validated('placement') === 'placed', fn ($query) => $query->whereHas('latestPlacement'))
                ->latest('inventory_item_batch_id')->paginate(15)->withQueryString()->through(function (InventoryItemBatch $batch): InventoryItemBatch {
                    $batch->item->setAppends([]);

                    return $batch;
                }),
            'filters' => $request->safe()->only(['search', 'warehouse', 'placement', 'include_depleted']),
        ]);
    }

    public function show(InventoryItemBatch $batch): Response
    {
        $batch->load(['item' => fn ($query) => $query->withTrashed(), 'latestPlacement', 'placements' => fn ($query) => $query->latest('id')]);
        $batch->item->setAppends([]);

        return Inertia::render('Inventory/Warehouse/Show', ['batch' => $batch]);
    }

    public function store(StoreInventoryBatchPlacementRequest $request, InventoryItemBatch $batch, InventoryWarehouseService $warehouse): RedirectResponse
    {
        $warehouse->place($batch, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Warehouse location and counter-check recorded.']);

        return back();
    }

    public function label(InventoryItemBatch $batch): View
    {
        $batch->load(['item' => fn ($query) => $query->withTrashed(), 'latestPlacement']);

        return view('inventory.batch-label', ['batch' => $batch, 'printedAt' => now('Asia/Manila')]);
    }
}
