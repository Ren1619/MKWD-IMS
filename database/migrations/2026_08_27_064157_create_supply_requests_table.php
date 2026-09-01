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
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ris_no')->unique();
            $table->foreignId('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requester_reference_id')->nullable()->constrained('hris_references')->nullOnDelete();
            $table->string('requester_name');
            $table->string('office_name')->nullable();
            $table->string('responsibility_center_code')->nullable();
            $table->text('purpose');
            $table->date('date_needed')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
