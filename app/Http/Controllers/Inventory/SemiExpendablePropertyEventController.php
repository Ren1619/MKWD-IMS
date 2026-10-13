<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordSemiExpendableEventRequest;
use App\Models\InventoryAsset;
use App\Services\SemiExpendablePropertyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SemiExpendablePropertyEventController extends Controller
{
    public function __construct(private SemiExpendablePropertyService $properties) {}

    public function store(RecordSemiExpendableEventRequest $request, InventoryAsset $asset): RedirectResponse
    {
        $this->properties->record($asset, $request->validated(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semi-expendable property transaction recorded.']);

        return back();
    }
}
