<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreIssuanceLiquidationRequest;
use App\Models\IssuanceSlip;
use App\Services\IssuanceLiquidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IssuanceLiquidationController extends Controller
{
    public function transition(StoreIssuanceLiquidationRequest $request, IssuanceSlip $slip, IssuanceLiquidationService $service): RedirectResponse
    {
        $service->transition($slip, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'IS liquidation updated.']);

        return back();
    }

    public function print(Request $request, IssuanceSlip $slip): View
    {
        abort_unless($request->user()->canManageInventory() || $slip->supplyRequest->requester_user_id === $request->user()->id, 403);

        return view('inventory.issuance-liquidation', ['slip' => $slip]);
    }
}
