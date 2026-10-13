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
        Schema::create('issuance_slip_sequences', function (Blueprint $table): void {
            $table->date('date')->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });
        Schema::create('issuance_slips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supply_request_id')->constrained()->restrictOnDelete();
            $table->string('slip_no');
            $table->date('issued_at');
            $table->unique(['issued_at', 'slip_no']);
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->json('lines');
            $table->string('status')->default('pending')->index();
            $table->date('liquidation_date')->nullable();
            $table->json('history');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuance_slips');
        Schema::dropIfExists('issuance_slip_sequences');
    }
};
