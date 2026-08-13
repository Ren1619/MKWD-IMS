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
        Schema::create('inv_mjr_cats', function (Blueprint $table) {
            $table->id('inv_mjr_cat_id');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('inv_class_cats', function (Blueprint $table) {
            $table->id('inv_class_cat_id');
            $table->foreignId('inv_mjr_cat_id')
                ->constrained('inv_mjr_cats', 'inv_mjr_cat_id')
                ->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('inv_series_cats', function (Blueprint $table) {
            $table->id('inv_series_cat_id');
            $table->foreignId('inv_class_cat_id')
                ->constrained('inv_class_cats', 'inv_class_cat_id')
                ->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('inv_asset_cats', function (Blueprint $table) {
            $table->id('inv_asset_cat_id');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_asset_cats');
        Schema::dropIfExists('inv_series_cats');
        Schema::dropIfExists('inv_class_cats');
        Schema::dropIfExists('inv_mjr_cats');
    }
};
