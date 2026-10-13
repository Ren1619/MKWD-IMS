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
        Schema::table('property_accountability_documents', function (Blueprint $table) {
            $table->string('recipient_office')->nullable()->after('recipient_position');
            $table->string('supplier_name')->nullable()->after('recipient_office');
            $table->string('invoice_reference', 120)->nullable()->after('supplier_name');
            $table->string('fund_code', 100)->nullable()->after('invoice_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_accountability_documents', function (Blueprint $table) {
            $table->dropColumn(['recipient_office', 'supplier_name', 'invoice_reference', 'fund_code']);
        });
    }
};
