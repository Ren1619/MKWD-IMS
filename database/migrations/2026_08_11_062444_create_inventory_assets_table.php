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
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->id('inventory_asset_id');
            $table->foreignId('category_id')
                ->constrained('inv_asset_cats', 'inv_asset_cat_id')
                ->restrictOnDelete();
            $table->foreignId('current_custodian_reference_id')->nullable()
                ->constrained('hris_references')
                ->nullOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->string('property_number', 100)->nullable()->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('unit_of_measure', 50)->default('unit');
            $table->string('fund_cluster', 100)->nullable();
            $table->unsignedInteger('quantity_per_property_card')->default(1);
            $table->unsignedInteger('quantity_per_physical_count')->default(1);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('nature_of_occupancy')->nullable();
            $table->date('acquisition_date')->index();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('depreciation_useful_life_months')->default(60);
            $table->decimal('appraised_value', 12, 2)->nullable();
            $table->date('appraisal_date')->nullable();
            $table->decimal('impairment_losses', 12, 2)->default(0);
            $table->text('physical_count_remarks')->nullable();
            $table->string('disposal_method')->nullable();
            $table->decimal('disposal_value', 12, 2)->nullable();
            $table->string('loss_report_no', 100)->nullable();
            $table->date('loss_report_date')->nullable();
            $table->string('loss_type', 30)->nullable();
            $table->text('loss_circumstances')->nullable();
            $table->string('status', 30)->default('available')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_assets');
    }
};
