<?php

namespace Database\Factories;

use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyRequest>
 */
class SupplyRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ris_no' => fake()->unique()->numerify('RIS-2026-######'),
            'requester_user_id' => User::factory(),
            'requester_name' => fake()->name(),
            'purpose' => fake()->sentence(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }
}
