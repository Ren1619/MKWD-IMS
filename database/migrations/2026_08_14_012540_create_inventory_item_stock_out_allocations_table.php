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
        Schema::create('inventory_item_stock_out_allocations', function (Blueprint $table) {
            $table->id('inventory_item_stock_out_allocation_id');
            $table->unsignedBigInteger('inventory_item_stock_out_id');
            $table->unsignedBigInteger('inventory_item_batch_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->unique(['inventory_item_stock_out_id', 'inventory_item_batch_id'], 'inventory_stock_out_batch_unique');
            $table->foreign('inventory_item_stock_out_id', 'stock_out_allocations_stock_out_fk')
                ->references('inventory_item_stock_out_id')
                ->on('inventory_item_stock_outs')
                ->cascadeOnDelete();
            $table->foreign('inventory_item_batch_id', 'stock_out_allocations_batch_fk')
                ->references('inventory_item_batch_id')
                ->on('inventory_item_batches')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_stock_out_allocations');
    }
};
