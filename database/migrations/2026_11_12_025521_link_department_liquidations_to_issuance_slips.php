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
        Schema::table('department_liquidations', function (Blueprint $table) {
            $table->foreignId('issuance_slip_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('active_issuance_slip_id')->nullable()->unique()->constrained('issuance_slips')->restrictOnDelete();
            $table->string('is_no')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_liquidations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_issuance_slip_id');
            $table->dropConstrainedForeignId('issuance_slip_id');
            $table->dropColumn('is_no');
        });
    }
};
