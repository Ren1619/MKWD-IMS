<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveWarehouseRequest;
use App\Http\Requests\Inventory\WarehouseRegistryIndexRequest;
use App\Models\InventoryItemBatch;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseRegistryController extends Controller
{
    public function index(WarehouseRegistryIndexRequest $request): Response|JsonResponse
    {
        $data = [
            'warehouses' => Warehouse::query()->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->validated('search').'%';
                $query->where(fn ($match) => $match->where('name', 'like', $search)->orWhere('address', 'like', $search));
            })->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $request->safe()->only(['search']),
        ];

        return $request->expectsJson()
            ? response()->json($data)
            : Inertia::render('Inventory/Warehouse/Registry', $data);
    }

    public function store(SaveWarehouseRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $warehouse = Warehouse::query()->create($request->validated());
            $audit->record('inventory.warehouse.created', 'Registered stockroom '.$warehouse->name, $warehouse, newValues: $warehouse->toArray());
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stockroom registered.']);

        return back();
    }

    public function update(SaveWarehouseRequest $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $warehouse, $audit): void {
            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            $before = $warehouse->toArray();
            $warehouse->update($request->validated());
            $audit->record('inventory.warehouse.updated', 'Updated stockroom '.$warehouse->name, $warehouse, oldValues: $before, newValues: $warehouse->toArray());
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stockroom updated.']);

        return back();
    }

    public function destroy(Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage-inventory');
        DB::transaction(function () use ($warehouse, $audit): void {
            $warehouse = Warehouse::query()->whereKey($warehouse->id)->lockForUpdate()->firstOrFail();
            if (InventoryItemBatch::query()->where('quantity_remaining', '>', 0)->whereHas('latestPlacement', fn ($placement) => $placement->where('warehouse_id', $warehouse->id))->exists()) {
                throw ValidationException::withMessages(['warehouse' => 'Move all remaining batches to another stockroom before deleting this stockroom.']);
            }
            $before = $warehouse->toArray();
            $warehouse->delete();
            $audit->record('inventory.warehouse.deleted', 'Deleted stockroom '.$warehouse->name, $warehouse, oldValues: $before);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stockroom deleted. Storage history has been retained.']);

        return back();
    }
}
