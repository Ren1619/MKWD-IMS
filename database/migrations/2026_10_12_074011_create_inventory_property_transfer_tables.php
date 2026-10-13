<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_property_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no', 120)->nullable()->unique();
            $table->foreignId('inventory_asset_id')->constrained('inventory_assets', 'inventory_asset_id')->restrictOnDelete();
            $table->foreignId('source_document_id')->constrained('property_accountability_documents')->restrictOnDelete();
            $table->foreignId('recipient_reference_id')->constrained('hris_references')->restrictOnDelete();
            $table->string('transfer_type', 30);
            $table->text('reason');
            $table->string('destination_location');
            $table->date('desired_date');
            $table->string('status', 30)->default('submitted');
            $table->json('source_snapshot');
            $table->json('recipient_snapshot');
            $table->text('replacement_justification')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_document_id')->nullable()->constrained('property_accountability_documents')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['inventory_asset_id', 'status'], 'property_transfer_asset_status');
        });
        Schema::create('inventory_property_transfer_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_property_transfer_id')->constrained('inventory_property_transfers', indexName: 'ptr_action_transfer_fk')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('attestation');
            $table->json('evidence');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_property_transfer_actions');
        Schema::dropIfExists('inventory_property_transfers');
    }
};
