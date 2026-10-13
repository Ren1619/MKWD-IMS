<?php

namespace App\Http\Controllers\Inventory;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryDisposalIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryDisposalRequest;
use App\Http\Requests\Inventory\TransitionInventoryDisposalRequest;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryDisposal;
use App\Models\InventoryDisposalLine;
use App\SemiExpendablePhysicalStatus;
use App\Services\InventoryDisposalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDisposalController extends Controller
{
    public function __construct(private InventoryDisposalService $disposals) {}

    public function index(InventoryDisposalIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Disposals/Index', [
            'documents' => InventoryDisposal::query()->withCount('lines')
                ->when($request->filled('search'), fn ($query) => $query->where('document_no', 'like', '%'.$request->validated('search').'%'))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
                ->latest()->paginate(15)->withQueryString(),
            'assets' => InventoryAsset::query()->whereNull('current_custodian_reference_id')
                ->whereNotIn('lifecycle_status', [AssetLifecycleStatus::Disposed, AssetLifecycleStatus::Lost, AssetLifecycleStatus::TransferredOut])
                ->where(fn ($query) => $query->where('condition_status', AssetConditionStatus::NonUsable)->orWhereIn('lifecycle_status', [AssetLifecycleStatus::ForDisposal, AssetLifecycleStatus::Retired]))
                ->whereDoesntHave('activeBorrowing')
                ->whereDoesntHave('accountabilityDocuments', fn ($query) => $query->whereIn('status', ['active', 'pending_recipient']))
                ->where('accounting_status', '!=', AssetAccountingStatus::Derecognized)
                ->where(fn ($query) => $query->where('accounting_classification', AssetAccountingClassification::Ppe)->orWhere('physical_status', SemiExpendablePhysicalStatus::ForDisposal))
                ->whereNotIn('inventory_asset_id', InventoryDisposalLine::query()->select('inventory_asset_id')->whereNotNull('inventory_asset_id')->whereHas('document', fn ($query) => $query->whereIn('status', ['submitted', 'approved'])))
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->validated('search').'%')->orWhere('property_number', 'like', '%'.$request->validated('search').'%')))
                ->orderBy('name')->limit(100)->get()
                ->map(fn (InventoryAsset $asset): array => $asset->only(['inventory_asset_id', 'name', 'property_number', 'unit_of_measure', 'quantity_per_property_card'])),
            'employees' => HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->orderBy('name')->get()->map(fn (HrisReference $reference): array => $reference->toSelectionOption()),
            'filters' => $request->safe()->only(['search', 'status']),
        ]);
    }

    public function store(StoreInventoryDisposalRequest $request): RedirectResponse
    {
        $document = $this->disposals->create($request->validated(), $request->user());

        return to_route('inventory.disposals.show', $document);
    }

    public function show(InventoryDisposal $document): Response
    {
        return Inertia::render('Inventory/Disposals/Show', [
            'document' => $document->load(['lines', 'actions']),
            'allowedActions' => $this->disposals->allowedActions($document, auth()->user()),
            'employees' => HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->orderBy('name')->get()->map(fn (HrisReference $reference): array => $reference->toSelectionOption()),
            'today' => today()->toDateString(),
        ]);
    }

    public function transition(TransitionInventoryDisposalRequest $request, InventoryDisposal $document): RedirectResponse
    {
        $this->disposals->transition($document, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Waste-material disposal decision recorded.']);

        return back();
    }

    public function print(InventoryDisposal $document): View
    {
        return view('inventory.waste-material',['document' => $document->load(['lines', 'actions'])]);
    }
}
