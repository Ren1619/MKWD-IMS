<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('inventory_assets')
            ->select(['inventory_asset_id', 'acquisition_cost', 'acquisition_date'])
            ->orderBy('inventory_asset_id')
            ->chunkById(200, function ($assets): void {
                foreach ($assets as $asset) {
                    $isPpe = (float) ($asset->acquisition_cost ?? 0) >= 50000;

                    DB::table('inventory_assets')
                        ->where('inventory_asset_id', $asset->inventory_asset_id)
                        ->update([
                            'accounting_classification' => $asset->acquisition_cost === null || (float) $asset->acquisition_cost <= 0
                                ? 'needs_review'
                                : ($isPpe ? 'ppe' : 'semi_expendable'),
                            'residual_value_percentage' => $isPpe ? 5 : null,
                            'residual_value_basis' => $isPpe ? 'COA default residual value of 5%.' : null,
                            'available_for_use_date' => $isPpe ? $asset->acquisition_date : null,
                            'property_tag_uuid' => (string) Str::uuid(),
                        ]);
                }
            }, 'inventory_asset_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_assets')->update([
            'accounting_classification' => 'needs_review',
            'residual_value_percentage' => null,
            'residual_value_basis' => null,
            'available_for_use_date' => null,
            'property_tag_uuid' => null,
        ]);
    }
};
