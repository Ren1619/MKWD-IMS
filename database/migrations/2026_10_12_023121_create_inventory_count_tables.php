<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no')->nullable()->unique();
            $table->string('title');
            $table->date('scheduled_date');
            $table->string('memorandum_reference', 120);
            $table->text('team_details');
            $table->string('status')->default('planned')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cutoff_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_stock_controls', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('active_count_session_id')->nullable()->constrained('inventory_count_sessions')->restrictOnDelete();
        });
        Schema::create('inventory_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items', 'inventory_item_id')->restrictOnDelete();
            $table->string('tag_no')->unique();
            $table->string('stock_number');
            $table->string('item_name');
            $table->string('unit_of_measure', 50);
            $table->unsignedInteger('book_quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->unsignedInteger('before_count_issues')->default(0);
            $table->unsignedInteger('after_count_issues')->default(0);
            $table->unsignedInteger('segregated_receipts')->default(0);
            $table->decimal('adjustment_unit_cost', 12, 2)->nullable();
            $table->text('valuation_basis')->nullable();
            $table->unique(['inventory_count_session_id', 'inventory_item_id'], 'count_session_item_unique');
            $table->timestamps();
        });
        Schema::create('inventory_count_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition');
            $table->string('location');
            $table->string('aisle')->nullable();
            $table->string('bin')->nullable();
            $table->string('counter_name');
            $table->string('checker_name');
            $table->string('validator_name');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_count_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('action');
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('inventory_count_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_count_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_batch_id')->constrained('inventory_item_batches', 'inventory_item_batch_id')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_adjustments');
        Schema::dropIfExists('inventory_count_actions');
        Schema::dropIfExists('inventory_count_entries');
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_stock_controls');
        Schema::dropIfExists('inventory_count_sessions');
    }
};
