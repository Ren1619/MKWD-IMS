<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_entries', function (Blueprint $table): void {
            $table->unsignedInteger('observed_quantity')->nullable();
            $table->unsignedInteger('after_count_issues_at_observation')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_entries', function (Blueprint $table): void {
            $table->dropColumn(['observed_quantity', 'after_count_issues_at_observation']);
        });
    }
};
