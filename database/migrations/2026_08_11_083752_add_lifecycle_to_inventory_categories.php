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
        Schema::table('inv_class_cats', function (Blueprint $table) {
            $table->dropForeign(['inv_mjr_cat_id']);
            $table->foreign('inv_mjr_cat_id')->references('inv_mjr_cat_id')->on('inv_mjr_cats')->restrictOnDelete();
        });

        Schema::table('inv_series_cats', function (Blueprint $table) {
            $table->dropForeign(['inv_class_cat_id']);
            $table->foreign('inv_class_cat_id')->references('inv_class_cat_id')->on('inv_class_cats')->restrictOnDelete();
        });

        foreach (['inv_mjr_cats', 'inv_class_cats', 'inv_series_cats', 'inv_asset_cats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['inv_mjr_cats', 'inv_class_cats', 'inv_series_cats', 'inv_asset_cats'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        Schema::table('inv_series_cats', function (Blueprint $table) {
            $table->dropForeign(['inv_class_cat_id']);
            $table->foreign('inv_class_cat_id')->references('inv_class_cat_id')->on('inv_class_cats')->cascadeOnDelete();
        });

        Schema::table('inv_class_cats', function (Blueprint $table) {
            $table->dropForeign(['inv_mjr_cat_id']);
            $table->foreign('inv_mjr_cat_id')->references('inv_mjr_cat_id')->on('inv_mjr_cats')->cascadeOnDelete();
        });
    }
};
