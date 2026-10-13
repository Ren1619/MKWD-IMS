<?php

namespace Database\Factories;

use App\Models\ProcurementDelivery;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProcurementDelivery> */
class ProcurementDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return ['procurement_request_id' => ProcurementRequest::factory()->state(['status' => 'ordered', 'purchase_order_no' => 'PO-TEST']), 'document_no' => 'DEL-'.Str::uuid(), 'purchase_order_no' => 'PO-TEST',
            'supplier_name' => fake()->company(), 'invoice_number' => fake()->unique()->bothify('INV-#####'), 'delivery_reference' => fake()->unique()->bothify('DR-#####'),
            'received_at' => now(), 'receiving_department' => 'Operations', 'order_document_basis' => 'PO and TOR verified; NOA/NTP not applicable.',
            'notification_reference' => 'Joint inspection notified under LOG-1', 'packaging_findings' => 'Packaging intact.', 'created_by_user_id' => User::factory()->inventoryManager(),
            'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid())];
    }
}
