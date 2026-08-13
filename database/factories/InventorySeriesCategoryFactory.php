<?php

namespace Database\Factories;

use App\Models\InventoryClassCategory;
use App\Models\InventorySeriesCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySeriesCategory>
 */
class InventorySeriesCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inv_class_cat_id' => InventoryClassCategory::factory(),
            'code' => fake()->unique()->bothify('S-###'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
