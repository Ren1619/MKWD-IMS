<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $names = DB::table('inventory_batch_placements')->select('warehouse')->distinct()->pluck('warehouse');
        foreach ($names as $name) {
            $warehouse = DB::table('warehouses')->where('name', $name)->first();
            $id = $warehouse?->id ?? DB::table('warehouses')->insertGetId([
                ...(Schema::hasColumn('warehouses', 'code') ? ['code' => 'LEGACY-'.bin2hex(random_bytes(8))] : []),
                'name' => $name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_batch_placements')->where('warehouse', $name)->whereNull('warehouse_id')->update(['warehouse_id' => $id]);
        }
    }

    public function down(): void
    {
        // Retain imported registry records and historical links; the schema rollback removes them.
    }
};
