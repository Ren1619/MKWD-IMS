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
        Schema::create('inventory_item_batches', function (Blueprint $table) {
            $table->id('inventory_item_batch_id');
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items', 'inventory_item_id')
                ->cascadeOnDelete();
            $table->unsignedInteger('batch_number');
            $table->unsignedInteger('quantity_in');
            $table->unsignedInteger('quantity_remaining');
            $table->date('received_at')->index();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_batches');
    }
};
