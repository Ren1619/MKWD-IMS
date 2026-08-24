<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemBatch>
 */
class InventoryItemBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'batch_number' => 1,
            'quantity_in' => 10,
            'quantity_remaining' => 10,
            'unit_cost' => fake()->randomFloat(2, 1, 5000),
            'received_at' => today(),
            'expiration_date' => null,
            'source' => fake()->company(),
            'reference_no' => fake()->unique()->bothify('DR-#####'),
        ];
    }
}
