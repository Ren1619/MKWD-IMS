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
        Schema::table('inv_asset_cats', function (Blueprint $table) {
            $table->boolean('is_vehicle')->default(false)->after('is_active')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inv_asset_cats', function (Blueprint $table) {
            $table->dropIndex(['is_vehicle']);
            $table->dropColumn('is_vehicle');
        });
    }
};
