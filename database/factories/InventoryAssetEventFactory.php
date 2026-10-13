<?php

namespace Database\Factories;

use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAssetEvent>
 */
class InventoryAssetEventFactory extends Factory
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
            'event_type' => InventoryAssetEventType::Maintenance,
            'event_date' => fake()->dateTimeBetween('-1 year'),
            'reference_no' => fake()->bothify('REF-####'),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'notes' => fake()->sentence(),
        ];
    }
}
