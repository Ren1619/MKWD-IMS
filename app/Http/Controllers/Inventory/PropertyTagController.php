<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryAsset;
use App\Services\PropertyTagService;
use Illuminate\Contracts\View\View;

class PropertyTagController extends Controller
{
    public function __construct(private PropertyTagService $propertyTags) {}

    public function show(InventoryAsset $asset): View
    {
        return view('inventory.property-tag-scan', [
            'asset' => $asset,
        ]);
    }

    public function print(InventoryAsset $asset): View
    {
        return view('inventory.property-tag', [
            'asset' => $asset,
            'qrCodeDataUri' => $this->propertyTags->dataUri($asset),
            'scanUrl' => $this->propertyTags->scanUrl($asset),
        ]);
    }
}
