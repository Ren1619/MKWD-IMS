<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained()->restrictOnDelete();
            $table->string('document_no')->unique();
            $table->string('purchase_order_no');
            $table->string('supplier_name');
            $table->string('invoice_number', 120);
            $table->string('delivery_reference', 120);
            $table->dateTime('received_at');
            $table->string('receiving_department');
            $table->string('noa_reference', 120)->nullable();
            $table->string('ntp_reference', 120)->nullable();
            $table->text('order_document_basis');
            $table->string('notification_reference');
            $table->text('packaging_findings');
            $table->string('status')->default('received')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('inspection_acceptance_no')->nullable()->unique();
            $table->string('receiving_report_no')->nullable()->unique();
            $table->string('supplier_return_no')->nullable()->unique();
            $table->json('inspection')->nullable();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamp('supplier_returned_at')->nullable();
            $table->timestamp('endorsed_at')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->unique(['procurement_request_id', 'delivery_reference'], 'delivery_source_reference_unique');
        });
        Schema::create('procurement_delivery_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_delivery_id')->constrained()->restrictOnDelete();
            $table->foreignId('procurement_request_line_id')->constrained(indexName: 'delivery_order_line_fk')->restrictOnDelete();
            $table->string('item_name');
            $table->string('unit_of_measure');
            $table->text('specifications')->nullable();
            $table->unsignedInteger('quantity_delivered');
            $table->unsignedInteger('quantity_accepted')->nullable();
            $table->unsignedInteger('quantity_committed');
            $table->decimal('unit_cost', 12, 2);
            $table->date('expiration_date')->nullable();
            $table->string('condition')->nullable();
            $table->string('disposition')->nullable();
            $table->string('storage_location')->nullable();
            $table->string('disposition_reference')->nullable();
            $table->text('findings')->nullable();
            $table->foreignId('inventory_item_batch_id')->nullable()->unique()->constrained('inventory_item_batches', 'inventory_item_batch_id', indexName: 'delivery_batch_fk')->restrictOnDelete();
            $table->json('asset_ids')->nullable();
            $table->timestamps();
            $table->unique(['procurement_delivery_id', 'procurement_request_line_id'], 'delivery_line_unique');
        });
        Schema::create('procurement_delivery_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_delivery_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('remarks');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_delivery_actions');
        Schema::dropIfExists('procurement_delivery_lines');
        Schema::dropIfExists('procurement_deliveries');
    }
};
