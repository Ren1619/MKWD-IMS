<?php

namespace Database\Factories;

use App\Models\InventoryReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryReturn> */
class InventoryReturnFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_no' => 'RMS-'.fake()->unique()->uuid(),
            'document_date' => now('Asia/Manila')->toDateString(),
            'daily_number' => fake()->unique()->numberBetween(1, 100000),
            'return_date' => now('Asia/Manila')->toDateString(),
            'source_mode' => 'legacy',
            'returned_by_name' => fake()->name(),
            'department_name' => 'Operations',
            'endorsement_reference' => 'ENDORSE-1',
            'legacy_issue_reference' => 'LEGACY-IS-1',
            'reason' => 'Unused project materials.',
            'status' => 'submitted',
            'created_by_user_id' => User::factory()->inventoryManager(),
            'idempotency_key' => fake()->unique()->uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
