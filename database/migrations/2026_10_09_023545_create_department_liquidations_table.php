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
        Schema::table('inventory_item_stock_outs', function (Blueprint $table): void {
            $table->string('department_name')->nullable()->index();
        });
        Schema::create('department_liquidation_sequences', function (Blueprint $table): void {
            $table->date('date')->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });
        Schema::create('department_liquidations', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no');
            $table->date('liquidation_date');
            $table->unique(['liquidation_date', 'document_no']);
            $table->string('department')->index();
            $table->string('active_department')->nullable()->unique();
            $table->string('charge_code')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('requested_by_name');
            $table->string('status')->default('submitted')->index();
            $table->json('lines');
            $table->json('history');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_liquidations');
        Schema::dropIfExists('department_liquidation_sequences');
        Schema::table('inventory_item_stock_outs', function (Blueprint $table): void {
            $table->dropColumn('department_name');
        });
    }
};
