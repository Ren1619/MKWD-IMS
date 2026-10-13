<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batch_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_batch_id')->constrained('inventory_item_batches', 'inventory_item_batch_id', indexName: 'batch_placement_batch_fk')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('warehouse', 150)->index();
            $table->string('area', 150);
            $table->string('rack', 100)->nullable();
            $table->string('bin', 100)->nullable();
            $table->unsignedInteger('observed_quantity');
            $table->string('source_evidence_reference');
            $table->json('source_details');
            $table->text('remarks');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recorded_by_name');
            $table->timestamps();
            $table->unique(['inventory_item_batch_id', 'version'], 'batch_placement_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_batch_placements');
    }
};
