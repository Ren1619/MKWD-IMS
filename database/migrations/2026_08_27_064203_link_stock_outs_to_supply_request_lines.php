<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_stock_outs', function (Blueprint $table) {
            $table->foreignId('supply_request_line_id')->nullable()->after('inventory_item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_stock_outs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_request_line_id');
        });
    }
};
