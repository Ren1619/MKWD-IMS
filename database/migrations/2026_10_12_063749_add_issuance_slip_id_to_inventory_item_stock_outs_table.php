<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_stock_outs', function (Blueprint $table): void {
            $table->foreignId('issuance_slip_id')->nullable()->constrained('issuance_slips')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_stock_outs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('issuance_slip_id');
        });
    }
};
