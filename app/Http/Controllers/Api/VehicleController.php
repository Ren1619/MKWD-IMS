<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\InventoryAsset;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $vehicles = InventoryAsset::query()
            ->whereHas('category', fn ($query) => $query->where('is_vehicle', true))
            ->with([
                'category:inv_asset_cat_id,code,name',
                'subcategory:inventory_asset_subcategory_id,inventory_asset_category_id,code,name',
                'currentCustodian:id,code,name',
                'activeBorrowing.borrowerReference:id,code,name',
            ])
            ->orderBy('inventory_asset_id')
            ->get();

        return VehicleResource::collection($vehicles);
    }
}
