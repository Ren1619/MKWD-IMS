<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProcurementDeliveryIndexRequest;
use App\Http\Requests\Inventory\StoreProcurementDeliveryRequest;
use App\Http\Requests\Inventory\TransitionProcurementDeliveryRequest;
use App\Models\HrisReference;
use App\Models\ProcurementDelivery;
use App\Models\ProcurementDeliveryAction;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestLine;
use App\Services\ProcurementDeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementDeliveryController extends Controller
{
    public function __construct(private ProcurementDeliveryService $deliveries) {}

    public function index(ProcurementDeliveryIndexRequest $request): Response
    {
        return Inertia::render('Inventory/Deliveries/Index', [
            'documents' => ProcurementDelivery::query()->with('procurementRequest:id,pr_no,purchase_order_no')->withCount('lines')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated('status')))
                ->when($request->filled('procurement_request_id'), fn ($query) => $query->where('procurement_request_id', $request->validated('procurement_request_id')))
                ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested->where('document_no', 'like', '%'.$request->validated('search').'%')->orWhere('supplier_name', 'like', '%'.$request->validated('search').'%')->orWhere('purchase_order_no', 'like', '%'.$request->validated('search').'%')->orWhere('delivery_reference', 'like', '%'.$request->validated('search').'%')))
                ->latest()->paginate(15)->withQueryString(), 'filters' => $request->safe()->only(['search', 'status', 'procurement_request_id'])]);
    }

    public function create(ProcurementDeliveryIndexRequest $request): Response
    {
        $orders = ProcurementRequest::query()->where('document_kind', 'PR')->whereIn('status', ['ordered', 'delivered'])->whereNotNull('purchase_order_no')
            ->with(['lines' => fn ($query) => $query->withSum(['deliveryLines as delivery_committed_quantity' => fn ($lines) => $lines->whereHas('delivery', fn ($documents) => $documents->whereIn('status', ['received', 'inspected', 'accepted']))], 'quantity_committed')])
            ->orderByDesc('id')->get();

        return Inertia::render('Inventory/Deliveries/Create', ['orders' => $orders->map(fn (ProcurementRequest $order): array => [
            'id' => $order->id, 'pr_no' => $order->pr_no, 'purchase_order_no' => $order->purchase_order_no,
            'lines' => $order->lines->map(fn (ProcurementRequestLine $line): array => $this->lineOption($line))->all()])->all(),
            'selectedOrderId' => $request->integer('procurement_request_id') ?: null]);
    }

    public function store(StoreProcurementDeliveryRequest $request): RedirectResponse
    {
        $document = $this->deliveries->create($request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Shipment recorded for joint inspection. Stock is unchanged.']);

        return to_route('inventory.deliveries.show', $document);
    }

    /** @return array{id: int, item_name: string, unit_of_measure: string, item_kind: string, specifications: string|null, unit_cost: string, available_quantity: int} */
    private function lineOption(ProcurementRequestLine $line): array
    {
        return ['id' => $line->id, 'item_name' => $line->item_name, 'unit_of_measure' => $line->unit_of_measure, 'item_kind' => $line->item_kind,
            'specifications' => $line->specifications, 'unit_cost' => $line->estimated_unit_cost,
            'available_quantity' => max(0, $line->quantity - (int) $line->delivery_committed_quantity)];
    }

    public function show(ProcurementDelivery $delivery): Response
    {
        $delivery->load(['procurementRequest', 'lines.orderLine', 'actions' => fn ($query) => $query->orderBy('id')]);

        return Inertia::render('Inventory/Deliveries/Show', ['document' => $delivery, 'allowedActions' => $delivery->procurementRequest->trashed() ? [] : $this->deliveries->allowedActions($delivery, $delivery->procurementRequest, auth()->user()),
            'employees' => HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)->get()->map(fn (HrisReference $reference): array => $reference->toSelectionOption()),
            'today' => now('Asia/Manila')->toDateString()]);
    }

    public function transition(TransitionProcurementDeliveryRequest $request, ProcurementDelivery $delivery): RedirectResponse
    {
        $this->deliveries->transition($delivery, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery decision and evidence recorded.']);

        return back();
    }

    public function print(ProcurementDelivery $delivery, string $document): View
    {
        abort_if($document === 'supplier-return' && $delivery->supplier_return_no === null, 404);
        abort_if($document === 'evaluation' && $delivery->inspection === null, 404);

        if ($document === 'evaluation') {
            return view('inventory.inspection-evaluation', ['delivery' => $delivery->load(['lines', 'actions'])]);
        }
        if ($document === 'supplier-return') {
            $authorization = $delivery->actions()->where('action', 'authorize_supplier_return')->first();
            if ($authorization !== null) {
                return view('inventory.supplier-return-notice', ['delivery' => $delivery->load('actions'), 'authorization' => $authorization]);
            }
        }

        return view('inventory.delivery-document', ['delivery' => $delivery->load(['procurementRequest', 'lines', 'actions' => fn ($query) => $query->orderBy('id')]), 'document' => $document]);
    }

    public function printPostponement(ProcurementDelivery $delivery, ProcurementDeliveryAction $action): View
    {
        abort_unless($action->procurement_delivery_id === $delivery->id && $action->action === 'postpone', 404);

        return view('inventory.inspection-postponement', ['delivery' => $delivery->load('lines'), 'notice' => $action]);
    }
}
