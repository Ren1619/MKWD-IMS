<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryItemIndexRequest;
use App\Http\Requests\Inventory\ShowInventoryItemRequest;
use App\Http\Requests\Inventory\StockInInventoryItemRequest;
use App\Http\Requests\Inventory\StockOutInventoryItemRequest;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemReplenishmentRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\HrisReference;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Services\InventoryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class InventoryItemController extends Controller
{
    public function __construct(private InventoryStockService $stockService) {}

    public function index(InventoryItemIndexRequest $request): Response
    {
        $items = InventoryItem::query()
            ->when($request->string('records')->toString() === 'archived', fn ($query) => $query->onlyTrashed())
            ->with(['seriesCategory.classCategory.majorCategory', 'accountableReference:id,name,type', 'batches'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($nested) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $nested->where('name', 'like', $search)->orWhere('stock_number', 'like', $search);
            }))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->integer('class_category_id') > 0, fn ($query) => $query->whereHas(
                'seriesCategory',
                fn ($seriesQuery) => $seriesQuery->where('inv_class_cat_id', $request->integer('class_category_id')),
            ))
            ->when($request->string('alert')->toString() === 'low_stock', fn ($query) => $query->lowStock())
            ->when($request->string('alert')->toString() === 'expired', fn ($query) => $query->whereHas('batches', fn ($batchQuery) => $batchQuery
                ->where('quantity_remaining', '>', 0)
                ->whereDate('expiration_date', '<', today())))
            ->when($request->string('alert')->toString() === 'expiring', fn ($query) => $query->whereHas('batches', fn ($batchQuery) => $batchQuery
                ->where('quantity_remaining', '>', 0)
                ->whereBetween('expiration_date', [today(), today()->addDays(InventoryItem::EXPIRATION_WARNING_DAYS)])))
            ->latest('inventory_item_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Items/Index', [
            'items' => $items,
            'seriesCategories' => InventorySeriesCategory::query()
                ->active()
                ->with('classCategory.majorCategory')
                ->whereHas('classCategory', fn ($query) => $query->where('is_active', true)->whereHas('majorCategory', fn ($majorQuery) => $majorQuery->where('is_active', true)))
                ->orderBy('name')
                ->get(),
            'classCategories' => InventoryClassCategory::query()
                ->with('majorCategory:inv_mjr_cat_id,name')
                ->orderBy('name')
                ->get(['inv_class_cat_id', 'inv_mjr_cat_id', 'name']),
            'references' => HrisReference::query()->where('is_active', true)->orderBy('name')->get(['id', 'type', 'code', 'name']),
            'filters' => $request->safe()->only(['search', 'status', 'records', 'alert', 'class_category_id']),
        ]);
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $receipt = Arr::only($data, ['quantity', 'unit_cost', 'received_at', 'expiration_date', 'source', 'reference_no', 'batch_notes']);
            $item = InventoryItem::query()->create(Arr::except($data, ['quantity', 'unit_cost', 'received_at', 'expiration_date', 'source', 'reference_no', 'batch_notes']));
            $this->stockService->initialize($item, $receipt);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item created.']);

        return to_route('inventory.items.index');
    }

    public function show(ShowInventoryItemRequest $request, InventoryItem $item): JsonResponse
    {
        $item->load([
            'seriesCategory.classCategory.majorCategory',
            'accountableReference:id,type,code,name',
            'batches',
        ]);

        $releases = $item->stockOuts()
            ->with([
                'recipientReference:id,type,code,name',
                'allocations.batch',
            ])
            ->latest('inventory_item_stock_out_id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'item' => $item,
            'releases' => $releases,
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item updated.']);

        return to_route('inventory.items.index');
    }

    public function updateReplenishment(UpdateInventoryItemReplenishmentRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Replenishment settings updated.']);

        return to_route('inventory.items.index');
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        if ($item->quantity > 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This item cannot be archived while stock remains. Release the remaining quantity first.',
            ]);

            return to_route('inventory.items.index');
        }

        $item->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item archived.']);

        return to_route('inventory.items.index');
    }

    public function restore(InventoryItem $item): RedirectResponse
    {
        $item->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item restored.']);

        return to_route('inventory.items.index', ['records' => 'archived']);
    }

    public function stockIn(StockInInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->stockService->stockIn($item, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock received.']);

        return to_route('inventory.items.index');
    }

    public function stockOut(StockOutInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validated();
        $reference = isset($data['recipient_reference_id'])
            ? HrisReference::query()->whereKey($data['recipient_reference_id'])->firstOrFail()
            : null;
        $data['recipient_name'] ??= $reference?->name;

        try {
            $this->stockService->stockOut($item, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock released.']);

        return to_route('inventory.items.index');
    }
}
