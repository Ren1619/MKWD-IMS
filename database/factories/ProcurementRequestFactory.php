<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRequest>
 */
class ProcurementRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pr_no' => fake()->unique()->numerify('PR-2026-######'),
            'created_by_user_id' => User::factory()->inventoryManager(),
            'type' => 'replenishment',
            'source' => 'manual',
            'status' => 'draft',
            'purpose' => fake()->sentence(),
        ];
    }

    public function intake(): static
    {
        return $this->state(fn (): array => [
            'pr_no' => fake()->unique()->numerify('REQ-2026-######'),
            'status' => 'intake',
            'document_kind' => 'PR',
            'procurement_category' => 'supplies',
            'preprocurement' => ['approved_request_reference' => 'REQ-ENDUSER-1', 'end_user' => 'End user', 'department' => 'Operations', 'division' => 'PWSD', 'is_replacement' => false],
        ]);
    }
}
