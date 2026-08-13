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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id('inventory_item_id');
            $table->foreignId('series_category_id')
                ->constrained('inv_series_cats', 'inv_series_cat_id')
                ->restrictOnDelete();
            $table->foreignId('accountable_reference_id')->nullable()
                ->constrained('hris_references')
                ->nullOnDelete();
            $table->string('name');
            $table->string('stock_number')->nullable()->unique();
            $table->string('unit_of_measure', 50)->default('pc');
            $table->string('uacs_object_code', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->date('expiration_date')->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
