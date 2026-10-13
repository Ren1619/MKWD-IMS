<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryReturnIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryReturnRequest;
use App\Http\Requests\Inventory\TransitionInventoryReturnRequest;
use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryReturn;
use App\Services\InventoryReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryReturnController extends Controller
{
    public function __construct(private InventoryReturnService $returns) {}

    public function index(InventoryReturnIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Returns/Index', [
            'documents' => InventoryReturn::query()->withCount('lines')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('document_no', 'like', '%'.$request->validated('search').'%')
                    ->orWhere('returned_by_name', 'like', '%'.$request->validated('search').'%')
                    ->orWhere('department_name', 'like', '%'.$request->validated('search').'%')))
                ->latest()->paginate(15)->withQueryString(),
            'filters' => $request->safe()->only(['search', 'status']),
        ]);
    }

    public function create(InventoryReturnIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Returns/Create', [
            'sources' => $this->returns->sources($request->integer('supply_request_id') ?: null, $request->validated('search'))->paginate(20)
                ->through(fn ($source): array => [
                    'id' => $source->getKey(), 'item_name' => $source->stockOut->item->name,
                    'unit_of_measure' => $source->stockOut->item->unit_of_measure, 'stock_number' => $source->stockOut->item->stock_number,
                    'issue_reference' => $source->stockOut->ris_no ?? 'ISSUE-'.$source->stockOut->getKey(),
                    'issue_date' => $source->stockOut->stocked_out_at->toDateString(),
                    'recipient_name' => $source->stockOut->recipient_name, 'department_name' => $source->stockOut->department_name,
                    'batch_number' => $source->batch->batch_number, 'unit_cost' => $source->unit_cost,
                    'expiration_date' => $source->batch->expiration_date?->toDateString(),
                    'available_quantity' => max(0, $source->quantity - (int) $source->committed_quantity),
                ])->withQueryString(),
            'items' => InventoryItem::query()->where('status', 'active')->orderBy('name')->get(['inventory_item_id', 'name', 'stock_number', 'unit_of_measure']),
            'references' => HrisReference::query()->where('type', 'employee')->where('is_active', true)->orderBy('name')->get()
                ->map(fn (HrisReference $reference): array => $reference->toSelectionOption()),
            'filters' => $request->safe()->only(['search', 'supply_request_id']),
        ]);
    }

    public function store(StoreInventoryReturnRequest $request): RedirectResponse
    {
        $document = $this->returns->create($request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Return recorded for inspection. Stock will be posted after approval.']);

        return to_route('inventory.returns.show', $document);
    }

    public function show(InventoryReturn $document): Response
    {
        return Inertia::render('Inventory/Returns/Show', [
            'document' => $document->load(['lines.posting', 'actions' => fn ($query) => $query->orderBy('id')]),
            'allowedActions' => $this->returns->allowedActions($document, auth()->user()),
        ]);
    }

    public function transition(TransitionInventoryReturnRequest $request, InventoryReturn $document): RedirectResponse
    {
        $this->returns->transition($document, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Return decision recorded.']);

        return back();
    }

    public function print(InventoryReturn $document): View
    {
        return view('inventory.material-return', ['document' => $document->load(['lines.posting', 'actions' => fn ($query) => $query->orderBy('id')])]);
    }
}
