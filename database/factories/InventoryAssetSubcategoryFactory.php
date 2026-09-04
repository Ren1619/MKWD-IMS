<?php

namespace Database\Factories;

use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetSubcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAssetSubcategory>
 */
class InventoryAssetSubcategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_asset_category_id' => InventoryAssetCategory::factory(),
            'code' => fake()->unique()->bothify('AS-###'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
