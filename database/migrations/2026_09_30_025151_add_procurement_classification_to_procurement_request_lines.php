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
        Schema::table('procurement_request_lines', function (Blueprint $table) {
            $table->string('item_kind', 20)->default('consumable')->after('inventory_item_id')->index();
            $table->foreignId('asset_category_id')->nullable()->after('series_category_id')
                ->constrained('inv_asset_cats', 'inv_asset_cat_id')
                ->restrictOnDelete();
            $table->foreignId('asset_subcategory_id')->nullable()->after('asset_category_id')
                ->constrained('inventory_asset_subcategories', 'inventory_asset_subcategory_id')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('procurement_request_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_subcategory_id');
            $table->dropConstrainedForeignId('asset_category_id');
            $table->dropColumn('item_kind');
        });
    }
};
