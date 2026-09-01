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
        Schema::create('procurement_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_request_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items', 'inventory_item_id')->restrictOnDelete();
            $table->foreignId('series_category_id')->nullable()->constrained('inv_series_cats', 'inv_series_cat_id')->restrictOnDelete();
            $table->string('item_name');
            $table->text('specifications')->nullable();
            $table->string('unit_of_measure');
            $table->unsignedInteger('quantity');
            $table->decimal('estimated_unit_cost', 15, 2);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('actual_unit_cost', 15, 2)->nullable();
            $table->date('received_at')->nullable();
            $table->string('delivery_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_request_lines');
    }
};
