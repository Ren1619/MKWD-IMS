<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no')->unique();
            $table->date('document_date');
            $table->unsignedInteger('daily_number');
            $table->unique(['document_date', 'daily_number']);
            $table->date('return_date');
            $table->string('source_mode');
            $table->foreignId('returned_by_reference_id')->nullable()->constrained('hris_references')->nullOnDelete();
            $table->string('returned_by_name');
            $table->string('department_name');
            $table->string('endorsement_reference', 120);
            $table->string('legacy_issue_reference', 120)->nullable();
            $table->text('reason');
            $table->string('status')->default('submitted')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->string('inspection_reference', 120)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('accounting_endorsed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_return_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items', 'inventory_item_id')->restrictOnDelete();
            $table->foreignId('source_allocation_id')->nullable()->constrained('inventory_item_stock_out_allocations', 'inventory_item_stock_out_allocation_id', indexName: 'return_lines_source_fk')->restrictOnDelete();
            $table->foreignId('source_supply_request_id')->nullable()->constrained('supply_requests')->restrictOnDelete();
            $table->string('source_issue_reference', 120)->nullable();
            $table->string('stock_number');
            $table->string('item_name');
            $table->string('unit_of_measure', 50);
            $table->unsignedInteger('quantity_declared');
            $table->unsignedInteger('quantity_accepted')->nullable();
            $table->unsignedInteger('quantity_committed');
            $table->decimal('unit_cost', 12, 2);
            $table->date('expiration_date')->nullable();
            $table->text('valuation_basis')->nullable();
            $table->string('condition')->nullable();
            $table->string('storage_location')->nullable();
            $table->text('inspection_remarks')->nullable();
            $table->timestamps();
            $table->index(['source_allocation_id', 'quantity_committed'], 'return_source_commitment_index');
        });
        Schema::create('inventory_return_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_return_line_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_batch_id')->unique()->constrained('inventory_item_batches', 'inventory_item_batch_id', indexName: 'return_allocations_batch_fk')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();
        });
        Schema::create('inventory_return_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_return_actions');
        Schema::dropIfExists('inventory_return_allocations');
        Schema::dropIfExists('inventory_return_lines');
        Schema::dropIfExists('inventory_returns');
    }
};
