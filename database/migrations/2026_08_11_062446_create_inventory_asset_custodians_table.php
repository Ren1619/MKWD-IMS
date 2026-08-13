<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_asset_custodians', function (Blueprint $table) {
            $table->id('inventory_asset_custodian_id');
            $table->foreignId('inventory_asset_id')
                ->constrained('inventory_assets', 'inventory_asset_id')
                ->cascadeOnDelete();
            $table->foreignId('hris_reference_id')
                ->constrained('hris_references')
                ->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(
                ['inventory_asset_id', 'unassigned_at'],
                'inv_asset_cust_active_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_custodians');
    }
};
