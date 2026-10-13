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
        Schema::table('supply_requests', function (Blueprint $table) {
            Schema::table('supply_requests', function (Blueprint $table): void {
                $table->string('return_slip_no', 100)->nullable();
                $table->date('return_slip_date')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            Schema::table('supply_requests', function (Blueprint $table): void {
                $table->dropColumn(['return_slip_no', 'return_slip_date']);
            });
        });
    }
};
