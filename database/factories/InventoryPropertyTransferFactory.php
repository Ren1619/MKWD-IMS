<?php

namespace Database\Factories;

use App\Models\HrisReference;
use App\Models\InventoryPropertyTransfer;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryPropertyTransfer> */
class InventoryPropertyTransferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_no' => fake()->unique()->bothify('PTR-####-######'),
            'source_document_id' => PropertyAccountabilityDocument::factory(),
            'inventory_asset_id' => fn (array $attributes) => PropertyAccountabilityDocument::query()->whereKey($attributes['source_document_id'])->firstOrFail()->inventory_asset_id,
            'recipient_reference_id' => HrisReference::factory(),
            'transfer_type' => 'reassignment', 'reason' => fake()->sentence(),
            'destination_location' => 'Operations', 'desired_date' => today(),
            'source_snapshot' => [], 'recipient_snapshot' => [],
            'created_by_user_id' => User::factory()->inventoryManager(),
        ];
    }
}
