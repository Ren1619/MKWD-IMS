<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryPropertyTransferIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryPropertyTransferRequest;
use App\Http\Requests\Inventory\TransitionInventoryPropertyTransferRequest;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryPropertyTransfer;
use App\Services\InventoryPropertyTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryPropertyTransferController extends Controller
{
    public function __construct(private InventoryPropertyTransferService $transfers) {}

    public function index(InventoryPropertyTransferIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Transfers/Index', [
            'transfers' => InventoryPropertyTransfer::query()
                ->when($request->filled('search'), fn ($query) => $query->where('document_no', 'like', '%'.$request->validated('search').'%'))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
                ->latest()->paginate(15)->withQueryString(),
            'assets' => InventoryAsset::query()->whereNotNull('current_custodian_reference_id')
                ->when($request->filled('asset'), fn ($query) => $query->whereKey($request->integer('asset')))
                ->when(! $request->filled('asset') && $request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->validated('search').'%')->orWhere('property_number', 'like', '%'.$request->validated('search').'%')))
                ->orderBy('name')->limit(100)->get(['inventory_asset_id', 'property_number', 'name', 'current_custodian_reference_id', 'location'])
                ->map(fn (InventoryAsset $asset): array => $asset->only(['inventory_asset_id', 'property_number', 'name', 'current_custodian_reference_id', 'location'])),
            'employees' => HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->orderBy('name')->get()->map(fn (HrisReference $reference): array => $reference->toSelectionOption()),
            'filters' => $request->safe()->only(['search', 'status', 'asset']),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StoreInventoryPropertyTransferRequest $request): RedirectResponse
    {
        $transfer = $this->transfers->create($request->validated(), $request->user());

        return to_route('inventory.transfers.show', $transfer);
    }

    public function show(InventoryPropertyTransfer $transfer): Response
    {
        return Inertia::render('Inventory/Transfers/Show', [
            'transfer' => $transfer->load(['actions', 'replacementDocument']),
            'allowedActions' => $this->transfers->allowedActions($transfer, auth()->user()),
            'today' => today()->toDateString(),
        ]);
    }

    public function transition(TransitionInventoryPropertyTransferRequest $request, InventoryPropertyTransfer $transfer): RedirectResponse
    {
        $this->transfers->transition($transfer, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property transfer decision recorded.']);

        return back();
    }

    public function print(InventoryPropertyTransfer $transfer): View
    {
        return view('inventory.property-transfer', ['transfer' => $transfer->load(['actions', 'replacementDocument'])]);
    }
}
