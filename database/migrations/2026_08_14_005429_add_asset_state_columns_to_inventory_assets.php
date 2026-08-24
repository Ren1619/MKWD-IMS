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
            $table->string('lifecycle_status', 30)->default('active')->after('status')->index();
            $table->string('condition_status', 30)->default('good')->after('lifecycle_status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn(['lifecycle_status', 'condition_status']);
        });
    }
};
