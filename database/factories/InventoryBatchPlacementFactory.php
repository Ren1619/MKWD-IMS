<?php

namespace Database\Factories;

use App\Models\InventoryBatchPlacement;
use App\Models\InventoryItemBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBatchPlacement> */
class InventoryBatchPlacementFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'inventory_item_batch_id' => InventoryItemBatch::factory(), 'version' => 1, 'warehouse' => 'Main Warehouse', 'area' => 'Maintenance materials',
            'rack' => 'A', 'bin' => '1', 'observed_quantity' => 10, 'source_evidence_reference' => 'SIGNED-RR-'.fake()->unique()->numerify('####'),
            'source_details' => [], 'remarks' => 'Quantity and description checked against source document.',
            'recorded_by_user_id' => User::factory()->inventoryManager(), 'recorded_by_name' => fake()->name(),
        ];
    }
}
