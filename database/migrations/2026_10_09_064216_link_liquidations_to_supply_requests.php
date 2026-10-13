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
            Schema::table('department_liquidations', function (Blueprint $table): void {
                $table->foreignId('supply_request_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('active_supply_request_id')->nullable()->unique()->constrained('supply_requests')->restrictOnDelete();
                $table->string('isf_no')->nullable();
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
            Schema::table('department_liquidations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('active_supply_request_id');
                $table->dropConstrainedForeignId('supply_request_id');
                $table->dropColumn(['isf_no', 'return_slip_no', 'return_slip_date']);
            });
        });
    }
};
