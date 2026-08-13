<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryItemIndexRequest;
use App\Http\Requests\Inventory\StockInInventoryItemRequest;
use App\Http\Requests\Inventory\StockOutInventoryItemRequest;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Services\InventoryStockService;
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
            ->with(['seriesCategory.classCategory.majorCategory', 'accountableReference:id,name,type', 'batches'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($nested) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $nested->where('name', 'like', $search)->orWhere('stock_number', 'like', $search);
            }))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('inventory_item_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Items/Index', [
            'items' => $items,
            'seriesCategories' => InventorySeriesCategory::query()
                ->active()
                ->with('classCategory.majorCategory')
                ->whereHas('classCategory', fn ($query) => $query->active()->whereHas('majorCategory', fn ($majorQuery) => $majorQuery->active()))
                ->orderBy('name')
                ->get(),
            'references' => HrisReference::query()->where('is_active', true)->orderBy('name')->get(['id', 'type', 'code', 'name']),
            'filters' => $request->safe()->only(['search', 'status']),
        ]);
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            $quantity = (int) $data['quantity'];
            $item = InventoryItem::query()->create(Arr::except($data, ['quantity']));
            $this->stockService->initialize($item, $quantity);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item created.']);

        return to_route('inventory.items.index');
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $item->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item updated.']);

        return to_route('inventory.items.index');
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $item->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item deleted.']);

        return to_route('inventory.items.index');
    }

    public function stockIn(StockInInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->stockService->stockIn($item, $request->integer('quantity'), $request->validated('received_at'));
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
