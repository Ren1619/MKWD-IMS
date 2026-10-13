<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('inventory_assets')
            ->where('accounting_classification', 'semi_expendable')
            ->orderBy('inventory_asset_id')
            ->chunkById(250, function ($assets): void {
                foreach ($assets as $asset) {
                    $isIssued = $asset->current_custodian_reference_id !== null;

                    DB::table('inventory_assets')
                        ->where('inventory_asset_id', $asset->inventory_asset_id)
                        ->update([
                            'semi_expendable_value_class' => (float) $asset->acquisition_cost <= 5000 ? 'low' : 'high',
                            'accounting_status' => $isIssued ? 'issued_and_expensed' : 'on_hand',
                            'physical_status' => $isIssued ? 'issued' : 'in_property_custody',
                            'accountability_status' => $isIssued ? 'accountable' : 'unassigned',
                            'useful_life_effective_date' => $asset->acquisition_date,
                            'relief_status' => 'none',
                        ]);
                }
            }, 'inventory_asset_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_assets')
            ->where('accounting_classification', 'semi_expendable')
            ->update([
                'accounting_status' => 'recognized',
                'semi_expendable_value_class' => null,
                'physical_status' => null,
                'accountability_status' => null,
                'accountability_expires_at' => null,
                'useful_life_effective_date' => null,
                'relief_status' => null,
                'incident_reported_at' => null,
                'available_for_reissue' => false,
            ]);
    }
};
