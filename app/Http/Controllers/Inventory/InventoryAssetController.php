<?php

namespace App\Http\Controllers\Inventory;

use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AssignInventoryAssetRequest;
use App\Http\Requests\Inventory\BorrowInventoryAssetRequest;
use App\Http\Requests\Inventory\InventoryAssetIndexRequest;
use App\Http\Requests\Inventory\ReturnInventoryAssetRequest;
use App\Http\Requests\Inventory\StoreInventoryAssetRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetRequest;
use App\Http\Requests\Inventory\UpdateInventoryAssetStateRequest;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Services\InventoryAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class InventoryAssetController extends Controller
{
    public function __construct(private InventoryAssetService $assetService) {}

    public function index(InventoryAssetIndexRequest $request): Response
    {
        $assets = InventoryAsset::query()
            ->when($request->string('records')->toString() === 'archived', fn ($query) => $query->onlyTrashed())
            ->with(['category', 'currentCustodian:id,name,code', 'activeBorrowing.borrowerReference:id,name,code'])
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($nested) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $nested->where('name', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('property_number', 'like', $search);
            }))
            ->when($request->string('lifecycle_status')->isNotEmpty(), fn ($query) => $query->where('lifecycle_status', $request->string('lifecycle_status')->toString()))
            ->when($request->string('condition_status')->isNotEmpty(), fn ($query) => $query->where('condition_status', $request->string('condition_status')->toString()))
            ->when($request->string('custody_status')->toString() === 'borrowed', fn ($query) => $query->whereHas('activeBorrowing'))
            ->when($request->string('custody_status')->toString() === 'assigned', fn ($query) => $query->whereNotNull('current_custodian_reference_id')->whereDoesntHave('activeBorrowing'))
            ->when($request->string('custody_status')->toString() === 'available', fn ($query) => $query->whereNull('current_custodian_reference_id')->whereDoesntHave('activeBorrowing'))
            ->latest('inventory_asset_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Assets/Index', [
            'assets' => $assets,
            'categories' => InventoryAssetCategory::query()->active()->orderBy('name')->get(),
            'employees' => HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'filters' => $request->safe()->only([
                'search',
                'records',
                'lifecycle_status',
                'condition_status',
                'custody_status',
            ]),
            'assetStateOptions' => [
                'lifecycles' => array_map(
                    fn (AssetLifecycleStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    AssetLifecycleStatus::cases(),
                ),
                'conditions' => array_map(
                    fn (AssetConditionStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    AssetConditionStatus::cases(),
                ),
            ],
        ]);
    }

    public function store(StoreInventoryAssetRequest $request): RedirectResponse
    {
        InventoryAsset::query()->create($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset created.']);

        return to_route('inventory.assets.index');
    }

    public function update(UpdateInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        $asset->update($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset updated.']);

        return to_route('inventory.assets.index');
    }

    public function destroy(InventoryAsset $asset): RedirectResponse
    {
        if ($asset->current_custodian_reference_id !== null || $asset->activeBorrowing()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This asset cannot be archived while assigned or borrowed. Return and unassign it first.',
            ]);

            return to_route('inventory.assets.index');
        }

        $asset->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset archived.']);

        return to_route('inventory.assets.index');
    }

    public function restore(InventoryAsset $asset): RedirectResponse
    {
        $asset->restore();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset restored.']);

        return to_route('inventory.assets.index', ['records' => 'archived']);
    }

    public function assign(AssignInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        $reference = HrisReference::query()->findOrFail($request->integer('hris_reference_id'));

        try {
            $this->assetService->assign($asset, $reference, $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['hris_reference_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Custodian assigned.']);

        return to_route('inventory.assets.index');
    }

    public function unassign(InventoryAsset $asset): RedirectResponse
    {
        $this->assetService->unassign($asset, request()->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Custodian unassigned.']);

        return to_route('inventory.assets.index');
    }

    public function borrow(BorrowInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        $data = $request->validated();
        $reference = isset($data['borrower_reference_id'])
            ? HrisReference::query()->whereKey($data['borrower_reference_id'])->firstOrFail()
            : null;
        $data['borrower_name'] ??= $reference?->name;

        try {
            $this->assetService->borrow($asset, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['borrower_reference_id' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset borrowed.']);

        return to_route('inventory.assets.index');
    }

    public function returnBorrowed(ReturnInventoryAssetRequest $request, InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->returnBorrowed($asset, $request->validated('return_notes'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['return_notes' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset returned.']);

        return to_route('inventory.assets.index');
    }

    public function updateState(UpdateInventoryAssetStateRequest $request, InventoryAsset $asset): RedirectResponse
    {
        try {
            $this->assetService->updateState($asset, [
                'lifecycle_status' => $request->string('lifecycle_status')->toString(),
                'condition_status' => $request->string('condition_status')->toString(),
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lifecycle_status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asset state updated.']);

        return to_route('inventory.assets.index');
    }
}
