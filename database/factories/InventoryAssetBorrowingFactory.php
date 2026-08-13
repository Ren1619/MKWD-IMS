<?php

namespace Database\Factories;

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAssetBorrowing>
 */
class InventoryAssetBorrowingFactory extends Factory
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
            'borrower_reference_id' => HrisReference::factory(),
            'borrower_name' => fake()->name(),
            'status' => 'borrowed',
            'borrowed_at' => now(),
            'due_at' => now()->addWeek(),
        ];
    }
}
