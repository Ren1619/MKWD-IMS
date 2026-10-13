<?php

namespace Database\Factories;

use App\Models\InventoryMonthlyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InventoryMonthlyReport> */
class InventoryMonthlyReportFactory extends Factory
{
    public function definition(): array
    {
        return ['document_no' => 'MONTHLY-'.Str::uuid(), 'period_month' => now('Asia/Manila')->subMonthNoOverflow()->startOfMonth()->toDateString(), 'scope_key' => hash('sha256', (string) Str::uuid()), 'revision' => 1, 'fund_cluster' => '01', 'classification_name' => 'All inventory classifications', 'include_zero' => true,
            'prepared_by_user_id' => User::factory()->inventoryManager(), 'checked_by_user_id' => User::factory()->inventoryManager(), 'noted_by_user_id' => User::factory()->inventoryManager(), 'approved_by_user_id' => User::factory()->inventoryManager(), 'signatories' => [], 'reconciliation_reference' => 'RECONCILE-1', 'idempotency_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid())];
    }
}
