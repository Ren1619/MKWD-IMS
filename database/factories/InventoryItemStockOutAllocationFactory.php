<?php

namespace Database\Factories;

use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\InventoryItemStockOutAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemStockOutAllocation>
 */
class InventoryItemStockOutAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_stock_out_id' => InventoryItemStockOut::factory(),
            'inventory_item_batch_id' => InventoryItemBatch::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_cost' => fake()->randomFloat(2, 1, 5000),
        ];
    }
}
