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
        DB::table('inventory_assets')->whereIn('status', ['available', 'assigned', 'borrowed'])->update([
            'lifecycle_status' => 'active',
            'condition_status' => 'good',
        ]);
        DB::table('inventory_assets')->where('status', 'maintenance')->update([
            'lifecycle_status' => 'under_maintenance',
            'condition_status' => 'needs_repair',
        ]);
        DB::table('inventory_assets')->where('status', 'non-usable')->update([
            'lifecycle_status' => 'retired',
            'condition_status' => 'non_usable',
        ]);
        DB::table('inventory_assets')->where('status', 'disposed')->update([
            'lifecycle_status' => 'disposed',
            'condition_status' => 'non_usable',
        ]);
        DB::table('inventory_assets')->where('status', 'defective')->update([
            'lifecycle_status' => 'under_maintenance',
            'condition_status' => 'defective',
        ]);
        DB::table('inventory_assets')->where('status', 'lost')->update([
            'lifecycle_status' => 'lost',
            'condition_status' => 'unknown',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('inventory_assets')->update(['status' => 'available']);
        DB::table('inventory_assets')->where('lifecycle_status', 'under_maintenance')->update(['status' => 'maintenance']);
        DB::table('inventory_assets')->where('lifecycle_status', 'retired')->update(['status' => 'non-usable']);
        DB::table('inventory_assets')->where('lifecycle_status', 'disposed')->update(['status' => 'disposed']);
        DB::table('inventory_assets')->where('lifecycle_status', 'lost')->update(['status' => 'lost']);
        DB::table('inventory_assets')->where('condition_status', 'defective')->update(['status' => 'defective']);
        DB::table('inventory_assets')
            ->where('lifecycle_status', 'active')
            ->whereNotNull('current_custodian_reference_id')
            ->update(['status' => 'assigned']);
        DB::table('inventory_assets')
            ->whereIn('inventory_asset_id', DB::table('inventory_asset_borrowings')->where('status', 'borrowed')->select('inventory_asset_id'))
            ->update(['status' => 'borrowed']);
    }
};
