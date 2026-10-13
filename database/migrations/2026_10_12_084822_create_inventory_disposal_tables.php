<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_disposals', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no', 120)->nullable()->unique();
            $table->string('place_of_storage');
            $table->string('method', 30);
            $table->text('reason');
            $table->string('status', 30)->default('submitted');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->json('certifier_snapshot');
            $table->string('certification_reference', 120);
            $table->text('certification_attestation');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('inventory_disposal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_disposal_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_asset_id')->nullable()->constrained('inventory_assets', 'inventory_asset_id')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('unit', 50);
            $table->text('description');
            $table->string('source_reference', 120);
            $table->json('source_snapshot')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->string('or_number', 120)->nullable();
            $table->decimal('proceeds', 14, 2)->nullable();
            $table->foreignId('disposal_event_id')->nullable()->constrained('inventory_asset_events')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['inventory_disposal_id', 'inventory_asset_id'], 'wmr_document_asset_unique');
        });
        Schema::create('inventory_disposal_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_disposal_id')->constrained()->restrictOnDelete();
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
        Schema::dropIfExists('inventory_disposal_actions');
        Schema::dropIfExists('inventory_disposal_lines');
        Schema::dropIfExists('inventory_disposals');
    }
};
