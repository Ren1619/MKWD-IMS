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
            $table->string('isf_no', 9)->nullable()->index();
            $table->date('isf_date')->nullable();
            $table->unsignedSmallInteger('isf_sequence')->nullable();
            $table->json('issuance_details')->nullable();
            $table->unique(['isf_date', 'isf_sequence']);
        });

        Schema::create('issuance_request_sequences', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuance_request_sequences');

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropUnique(['isf_date', 'isf_sequence']);
            $table->dropIndex(['isf_no']);
            $table->dropColumn(['isf_no', 'isf_date', 'isf_sequence', 'issuance_details']);
        });
    }
};
