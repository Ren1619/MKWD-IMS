<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inv_asset_cats', function (Blueprint $table) {
            $table->string('account_code', 50)->nullable()->after('description');
            $table->unsignedSmallInteger('default_useful_life_months')->default(60)->after('account_code');
            $table->decimal('default_residual_value_percentage', 5, 2)->default(5)->after('default_useful_life_months');
            $table->boolean('is_depreciable')->default(true)->after('default_residual_value_percentage');
        });

        DB::table('inv_asset_cats')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) = ?', ['land'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['land %'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%heritage%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%construction in progress%']);
            })
            ->update(['is_depreciable' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_asset_cats', function (Blueprint $table) {
            $table->dropColumn([
                'account_code',
                'default_useful_life_months',
                'default_residual_value_percentage',
                'is_depreciable',
            ]);
        });
    }
};
