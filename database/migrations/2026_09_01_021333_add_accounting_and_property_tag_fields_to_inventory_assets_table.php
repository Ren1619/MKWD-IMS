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
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->uuid('property_tag_uuid')
                ->nullable()
                ->after('property_number')
                ->unique();
            $table->date('available_for_use_date')
                ->nullable()
                ->after('acquisition_date')
                ->index();
            $table->string('accounting_classification', 30)
                ->default('needs_review')
                ->after('acquisition_cost')
                ->index();
            $table->decimal('residual_value_percentage', 5, 2)
                ->nullable()
                ->after('accounting_classification');
            $table->text('residual_value_basis')
                ->nullable()
                ->after('residual_value_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropUnique(['property_tag_uuid']);
            $table->dropIndex(['available_for_use_date']);
            $table->dropIndex(['accounting_classification']);
            $table->dropColumn([
                'property_tag_uuid',
                'available_for_use_date',
                'accounting_classification',
                'residual_value_percentage',
                'residual_value_basis',
            ]);
        });
    }
};
