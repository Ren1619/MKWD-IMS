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
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pr_no')->unique();
            $table->foreignId('supply_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('source')->default('manual');
            $table->string('status')->default('draft')->index();
            $table->text('purpose');
            $table->string('funding_source')->nullable();
            $table->string('responsibility_center_code')->nullable();
            $table->string('ppmp_reference')->nullable();
            $table->string('app_reference')->nullable();
            $table->string('app_cse_classification')->nullable();
            $table->string('procurement_mode')->nullable();
            $table->string('purchase_order_no')->nullable();
            $table->string('inspection_acceptance_no')->nullable();
            $table->date('required_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_requests');
    }
};
