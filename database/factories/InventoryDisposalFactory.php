<?php

namespace Database\Factories;

use App\Models\InventoryDisposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryDisposal> */
class InventoryDisposalFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['document_no' => fake()->unique()->bothify('WMR-####-######'), 'place_of_storage' => 'Warehouse',
            'method' => 'destroyed', 'reason' => fake()->sentence(), 'created_by_user_id' => User::factory()->inventoryManager(),
            'certifier_snapshot' => ['name' => fake()->name(), 'position' => 'Property custodian'], 'certification_reference' => fake()->bothify('CERT-######'),
            'certification_attestation' => 'Waste quantity and source inspected and certified.'];
    }
}
