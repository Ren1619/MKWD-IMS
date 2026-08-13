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
        Schema::create('inventory_asset_borrowings', function (Blueprint $table) {
            $table->id('inventory_asset_borrowing_id');
            $table->foreignId('inventory_asset_id')
                ->constrained('inventory_assets', 'inventory_asset_id')
                ->cascadeOnDelete();
            $table->foreignId('borrower_reference_id')->nullable()
                ->constrained('hris_references')
                ->nullOnDelete();
            $table->string('borrower_name')->nullable();
            $table->string('status', 30)->default('borrowed')->index();
            $table->text('notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamp('borrowed_at');
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_borrowings');
    }
};
