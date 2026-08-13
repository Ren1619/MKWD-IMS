<?php

namespace Database\Factories;

use App\Models\InventoryClassCategory;
use App\Models\InventoryMajorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryClassCategory>
 */
class InventoryClassCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inv_mjr_cat_id' => InventoryMajorCategory::factory(),
            'code' => fake()->unique()->bothify('C-###'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
