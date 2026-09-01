<?php

namespace Database\Factories;

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetCustodian;
use App\Models\PropertyAccountabilityDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyAccountabilityDocument>
 */
class PropertyAccountabilityDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_no' => fake()->unique()->bothify('PAR-####-######'),
            'document_type' => 'PAR',
            'inventory_asset_id' => InventoryAsset::factory(),
            'inventory_asset_custodian_id' => InventoryAssetCustodian::factory(),
            'recipient_reference_id' => HrisReference::factory(),
            'issued_by_user_id' => User::factory()->inventoryManager(),
            'status' => 'pending_recipient',
            'entity_name' => config('app.name'),
            'asset_name' => fake()->words(3, true),
            'property_number' => fake()->unique()->bothify('PROP-######'),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'unit_of_measure' => 'unit',
            'quantity' => 1,
            'acquisition_date' => fake()->dateTimeBetween('-5 years'),
            'acquisition_cost' => fake()->randomFloat(2, 50000, 500000),
            'estimated_useful_life_months' => 60,
            'recipient_name' => fake()->name(),
            'recipient_code' => fake()->numerify('EMP-####'),
            'issued_by_name' => fake()->name(),
            'issuer_attestation' => 'I certify that this property was issued to the named recipient.',
            'issued_at' => now(),
            'renewal_due_at' => now()->addYears(3),
        ];
    }
}
