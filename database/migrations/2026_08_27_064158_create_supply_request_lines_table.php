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
        Schema::create('supply_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items', 'inventory_item_id')->restrictOnDelete();
            $table->boolean('is_new_item')->default(false);
            $table->string('item_name');
            $table->text('specifications')->nullable();
            $table->string('unit_of_measure');
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_released')->default(0);
            $table->decimal('estimated_unit_cost', 15, 2)->nullable();
            $table->text('justification')->nullable();
            $table->string('planning_classification')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_request_lines');
    }
};
