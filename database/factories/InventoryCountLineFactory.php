<?php

namespace Database\Factories;

use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryCountLine> */
class InventoryCountLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'inventory_count_session_id' => InventoryCountSession::factory(),
            'inventory_item_id' => InventoryItem::factory()->state(['quantity' => 0]),
            'tag_no' => 'TAG-'.fake()->unique()->uuid(),
            'stock_number' => fake()->numerify('STOCK-######'),
            'item_name' => fake()->words(3, true),
            'unit_of_measure' => 'pc',
            'book_quantity' => 0,
            'unit_cost' => '0.00',
        ];
    }
}
