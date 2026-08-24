<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryStatusRequest;
use App\InventoryCategoryType;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryMajorCategory;
use App\Services\InventoryCategoryManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCategoryController extends Controller
{
    public function __construct(private InventoryCategoryManager $categoryManager) {}

    public function index(): Response
    {
        return Inertia::render('Inventory/Categories/Index', [
            'majorCategories' => InventoryMajorCategory::query()
                ->withCount('classCategories')
                ->with(['classCategories' => fn ($query) => $query
                    ->withCount('seriesCategories')
                    ->with(['seriesCategories' => fn ($seriesQuery) => $seriesQuery->withCount('items')->orderBy('name')])
                    ->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'assetCategories' => InventoryAssetCategory::query()->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInventoryCategoryRequest $request): RedirectResponse
    {
        $type = InventoryCategoryType::from($request->validated('type'));
        $this->categoryManager->create($type, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category created.']);

        return to_route('inventory.categories.index');
    }

    public function update(UpdateInventoryCategoryRequest $request, string $type, int $category): RedirectResponse
    {
        $categoryType = InventoryCategoryType::from($type);
        $this->categoryManager->update($categoryType, $category, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated.']);

        return to_route('inventory.categories.index');
    }

    public function updateStatus(UpdateInventoryCategoryStatusRequest $request, string $type, int $category): RedirectResponse
    {
        $categoryType = InventoryCategoryType::from($type);
        $this->categoryManager->updateStatus($categoryType, $category, $request->boolean('is_active'));
        $message = $request->boolean('is_active') ? 'Category activated.' : 'Category archived.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('inventory.categories.index');
    }

    public function destroy(string $type, int $category): RedirectResponse
    {
        $categoryType = InventoryCategoryType::from($type);
        $categoryModel = $this->categoryManager->find($categoryType, $category);

        if ($message = $this->categoryManager->deletionBlockReason($categoryType, $categoryModel)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return to_route('inventory.categories.index');
        }

        $categoryModel->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category deleted.']);

        return to_route('inventory.categories.index');
    }
}
