<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'series_category_id' => InventorySeriesCategory::factory(),
            'name' => fake()->words(3, true),
            'stock_number' => fake()->unique()->bothify('STK-#####'),
            'unit_of_measure' => fake()->randomElement(['pc', 'box', 'ream']),
            'quantity' => 0,
            'reorder_point' => 10,
            'reorder_quantity' => 25,
            'status' => 'active',
        ];
    }
}
