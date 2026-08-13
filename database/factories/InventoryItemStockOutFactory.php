<?php

namespace Database\Factories;

use App\Models\HrisReference;
use App\Models\InventoryItem;
use App\Models\InventoryItemStockOut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItemStockOut>
 */
class InventoryItemStockOutFactory extends Factory
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
            'recipient_reference_id' => HrisReference::factory(),
            'recipient_name' => fake()->name(),
            'ris_no' => fake()->unique()->numerify('RIS-#####'),
            'quantity' => fake()->numberBetween(1, 10),
            'stocked_out_at' => today(),
        ];
    }
}
