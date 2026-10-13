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
            $table->string('semi_expendable_value_class', 20)->nullable()->index()->after('accounting_classification');
            $table->string('physical_status', 40)->nullable()->index()->after('condition_status');
            $table->string('accountability_status', 40)->nullable()->index()->after('physical_status');
            $table->date('accountability_expires_at')->nullable()->index()->after('accountability_status');
            $table->date('useful_life_effective_date')->nullable()->after('accountability_expires_at');
            $table->text('useful_life_override_reason')->nullable()->after('useful_life_effective_date');
            $table->foreignId('useful_life_authorized_by_user_id')->nullable()->after('useful_life_override_reason')
                ->constrained('users', indexName: 'inv_assets_life_authorizer_fk')
                ->nullOnDelete();
            $table->string('relief_status', 30)->nullable()->index()->after('useful_life_authorized_by_user_id');
            $table->date('incident_reported_at')->nullable()->index()->after('relief_status');
            $table->boolean('available_for_reissue')->default(false)->index()->after('incident_reported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropForeign('inv_assets_life_authorizer_fk');
            $table->dropColumn([
                'semi_expendable_value_class',
                'physical_status',
                'accountability_status',
                'accountability_expires_at',
                'useful_life_effective_date',
                'useful_life_override_reason',
                'useful_life_authorized_by_user_id',
                'relief_status',
                'incident_reported_at',
                'available_for_reissue',
            ]);
        });
    }
};
