<?php

namespace Database\Factories;

use App\Models\InventoryAsset;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAsset>
 */
class InventoryAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => InventoryAssetCategory::factory(),
            'subcategory_id' => fn (array $attributes): int => InventoryAssetSubcategory::factory()->create([
                'inventory_asset_category_id' => $attributes['category_id'],
            ])->getKey(),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'property_number' => fake()->unique()->bothify('PROP-######'),
            'name' => fake()->words(3, true),
            'unit_of_measure' => 'unit',
            'brand' => fake()->company(),
            'model' => fake()->bothify('Model-##??'),
            'location' => fake()->city(),
            'acquisition_date' => fake()->dateTimeBetween('-5 years'),
            'acquisition_cost' => fake()->randomFloat(2, 1000, 100000),
            'depreciation_useful_life_months' => 60,
            'lifecycle_status' => 'active',
            'condition_status' => 'good',
        ];
    }
}
