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
        Schema::create('hris_references', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->string('type', 30)->index();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('parent_external_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['type', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hris_references');
    }
};
