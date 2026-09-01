<?php

namespace Database\Factories;

use App\Models\SupplyRequest;
use App\Models\SupplyRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyRequestLine>
 */
class SupplyRequestLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supply_request_id' => SupplyRequest::factory(),
            'is_new_item' => true,
            'item_name' => fake()->words(3, true),
            'unit_of_measure' => 'pc',
            'quantity_requested' => fake()->numberBetween(1, 20),
        ];
    }
}
