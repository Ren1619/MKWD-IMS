<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProcurementRequestRequest;
use App\Http\Requests\Inventory\TransitionProcurementRequestRequest;
use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use App\Models\ProcurementRequest;
use App\Services\ProcurementRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementRequestController extends Controller
{
    public function __construct(private ProcurementRequestWorkflow $workflow) {}

    public function index(): Response
    {
        return Inertia::render('Inventory/Procurement/Index', ['procurementRequests' => ProcurementRequest::query()->with(['lines.item', 'actions.actor:id,name', 'creator:id,name'])->latest()->paginate(15), 'items' => InventoryItem::query()->where('status', 'active')->orderBy('name')->get(['inventory_item_id', 'name', 'unit_of_measure', 'quantity', 'reorder_point', 'reorder_quantity']), 'seriesCategories' => InventorySeriesCategory::query()->active()->orderBy('name')->get(['inv_series_cat_id', 'name'])]);
    }

    public function store(StoreProcurementRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $record = ProcurementRequest::query()->create([...$request->safe()->except('lines'), 'pr_no' => 'TMP-'.Str::uuid(), 'created_by_user_id' => $request->user()->id, 'status' => 'draft']);
            $record->update(['pr_no' => sprintf('PR-%s-%06d', now()->format('Y'), $record->id)]);
            $record->lines()->createMany($request->validated('lines'));
            $record->actions()->create(['actor_user_id' => $request->user()->id, 'action' => 'prepare', 'to_status' => 'draft', 'attestation' => 'I certify this procurement request is complete and supported.']);
        });

        return back()->with('success', 'Procurement request prepared.');
    }

    public function transition(TransitionProcurementRequestRequest $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->workflow->transition($procurementRequest, $request->user(), $request->validated());

        return back()->with('success', 'Procurement workflow updated.');
    }
}
