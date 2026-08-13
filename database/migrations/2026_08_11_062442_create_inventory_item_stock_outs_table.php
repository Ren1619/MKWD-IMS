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
        Schema::create('inventory_item_stock_outs', function (Blueprint $table) {
            $table->id('inventory_item_stock_out_id');
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items', 'inventory_item_id')
                ->cascadeOnDelete();
            $table->foreignId('recipient_reference_id')->nullable()
                ->constrained('hris_references')
                ->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('ris_no', 100)->nullable()->index();
            $table->string('responsibility_center_code', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->date('stocked_out_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_stock_outs');
    }
};
