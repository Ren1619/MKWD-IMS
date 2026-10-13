<?php

namespace Database\Factories;

use App\Models\InventoryCountSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryCountSession> */
class InventoryCountSessionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_no' => 'COUNT-'.fake()->unique()->uuid(),
            'title' => 'Physical inventory',
            'scheduled_date' => now('Asia/Manila')->toDateString(),
            'memorandum_reference' => 'MEMO-'.fake()->numerify('######'),
            'team_details' => 'Warehouse counter, Accounting checker, Internal Control validator, and COA witness.',
            'status' => 'planned',
            'created_by_user_id' => User::factory()->inventoryManager(),
        ];
    }
}
