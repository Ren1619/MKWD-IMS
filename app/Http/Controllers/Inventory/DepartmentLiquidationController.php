<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DepartmentAllocationRequest;
use App\Http\Requests\Inventory\StoreDepartmentLiquidationRequest;
use App\Http\Requests\Inventory\TransitionDepartmentLiquidationRequest;
use App\Models\DepartmentLiquidation;
use App\Models\IssuanceSlip;
use App\Services\DepartmentLiquidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentLiquidationController extends Controller
{
    public function allocations(DepartmentAllocationRequest $request, DepartmentLiquidationService $service): JsonResponse
    {
        $slip = IssuanceSlip::query()->with('supplyRequest')->findOrFail($request->integer('issuance_slip_id'));
        $allocation = $service->allocation($slip, $request->string('date')->toString());

        return response()->json(['lines' => $allocation['lines'] ?? []]);
    }

    public function store(StoreDepartmentLiquidationRequest $request, DepartmentLiquidationService $service): RedirectResponse
    {
        $service->submit($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'IS liquidation submitted.']);

        return back();
    }

    public function transition(TransitionDepartmentLiquidationRequest $request, DepartmentLiquidation $document, DepartmentLiquidationService $service): RedirectResponse
    {
        $service->transition($document, $request->user(), $request->string('action')->toString(), $request->string('remarks')->toString() ?: null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'IS liquidation updated.']);

        return back();
    }

    public function print(Request $request, DepartmentLiquidation $document): View
    {
        abort_unless($request->user()->canManageInventory(), 403);

        return view('inventory.department-liquidation', ['document' => $document]);
    }
}
