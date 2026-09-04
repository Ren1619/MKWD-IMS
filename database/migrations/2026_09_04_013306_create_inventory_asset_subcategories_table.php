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
        Schema::create('inventory_asset_subcategories', function (Blueprint $table) {
            $table->id('inventory_asset_subcategory_id');
            $table->foreignId('inventory_asset_category_id')
                ->constrained(
                    table: 'inv_asset_cats',
                    column: 'inv_asset_cat_id',
                    indexName: 'inv_asset_subcat_category_fk',
                )
                ->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unique(
                ['inventory_asset_category_id', 'name'],
                'inv_asset_subcat_category_name_unique',
            );
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_subcategories');
    }
};
