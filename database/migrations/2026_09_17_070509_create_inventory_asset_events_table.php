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
        Schema::create('inventory_asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_asset_id')
                ->constrained('inventory_assets', 'inventory_asset_id')
                ->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->date('event_date')->index();
            $table->string('reference_no', 120)->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('condition_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('details')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_asset_id', 'idempotency_key'], 'asset_event_idempotency_unique');
            $table->index(['inventory_asset_id', 'event_date'], 'asset_event_timeline_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_events');
    }
};
