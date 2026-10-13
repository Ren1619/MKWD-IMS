<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RecordInventoryAssetEventRequest;
use App\Models\InventoryAsset;
use App\Services\InventoryAssetLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class InventoryAssetEventController extends Controller
{
    public function __construct(private InventoryAssetLifecycleService $lifecycle) {}

    public function store(
        RecordInventoryAssetEventRequest $request,
        InventoryAsset $asset,
    ): RedirectResponse {
        try {
            $event = $this->lifecycle->record($asset, $request->validated(), $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['event_type' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $event->event_type->label().' recorded.',
        ]);

        return to_route('inventory.assets.index');
    }
}
