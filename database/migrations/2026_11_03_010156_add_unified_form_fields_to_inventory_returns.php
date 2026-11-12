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
        Schema::table('inventory_returns', function (Blueprint $table) {
            $table->unsignedTinyInteger('workflow_version')->default(1);
        });
        Schema::table('inventory_return_lines', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('warehouse_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_return_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('warehouse_name');
        });
        Schema::table('inventory_returns', function (Blueprint $table) {
            $table->dropColumn('workflow_version');
        });
    }
};
