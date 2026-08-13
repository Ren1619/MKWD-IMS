<?php

namespace Database\Factories;

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCustodian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAssetCustodian>
 */
class InventoryAssetCustodianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_asset_id' => InventoryAsset::factory(),
            'hris_reference_id' => HrisReference::factory(),
            'assigned_at' => now(),
        ];
    }
}
