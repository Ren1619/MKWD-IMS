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
        Schema::create('property_accountability_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_no')->unique();
            $table->string('document_type', 3)->index();
            $table->foreignId('inventory_asset_id')->constrained(
                table: 'inventory_assets',
                column: 'inventory_asset_id',
                indexName: 'pa_docs_asset_fk',
            )->restrictOnDelete();
            $table->foreignId('inventory_asset_custodian_id')->constrained(
                table: 'inventory_asset_custodians',
                column: 'inventory_asset_custodian_id',
                indexName: 'pa_docs_custodian_fk',
            )->restrictOnDelete();
            $table->foreignId('recipient_reference_id')->constrained(
                table: 'hris_references',
                indexName: 'pa_docs_recipient_fk',
            )->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->constrained(
                table: 'users',
                indexName: 'pa_docs_issuer_fk',
            )->restrictOnDelete();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained(
                table: 'users',
                indexName: 'pa_docs_acknowledger_fk',
            )->nullOnDelete();
            $table->foreignId('supersedes_document_id')->nullable()->constrained(
                table: 'property_accountability_documents',
                indexName: 'pa_docs_supersedes_fk',
            )->nullOnDelete();
            $table->string('status')->default('pending_recipient')->index();
            $table->string('entity_name');
            $table->string('fund_cluster')->nullable();
            $table->string('asset_name');
            $table->text('asset_description')->nullable();
            $table->string('property_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('unit_of_measure');
            $table->unsignedInteger('quantity');
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 15, 2);
            $table->unsignedInteger('estimated_useful_life_months')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_code')->nullable();
            $table->string('recipient_position')->nullable();
            $table->string('issued_by_name');
            $table->text('issuer_attestation');
            $table->text('recipient_attestation')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('renewal_due_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps();

            $table->index(['inventory_asset_id', 'status'], 'property_accountability_asset_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_accountability_documents');
    }
};
