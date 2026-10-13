<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_property_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipient_reference_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('inventory_property_transfers')->whereNull('recipient_reference_id')->exists()) {
            throw new RuntimeException('Retain external PTR records; recipient references cannot be made mandatory while agency recipients exist.');
        }
        Schema::table('inventory_property_transfers', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipient_reference_id')->nullable(false)->change();
        });
    }
};
