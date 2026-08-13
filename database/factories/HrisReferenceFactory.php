<?php

namespace Database\Factories;

use App\Models\HrisReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HrisReference>
 */
class HrisReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->uuid(),
            'type' => HrisReference::TYPE_EMPLOYEE,
            'code' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'source_updated_at' => now(),
            'last_synced_at' => now(),
        ];
    }

    public function department(): static
    {
        return $this->state(fn (): array => [
            'type' => HrisReference::TYPE_DEPARTMENT,
            'code' => fake()->unique()->lexify('DEPT-???'),
            'name' => fake()->company(),
            'email' => null,
        ]);
    }
}
