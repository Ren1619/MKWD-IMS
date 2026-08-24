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
        Schema::table('inventory_item_batches', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity_remaining');
            $table->date('expiration_date')->nullable()->after('received_at')->index();
            $table->string('source')->nullable()->after('expiration_date');
            $table->string('reference_no', 100)->nullable()->after('source')->index();
            $table->text('notes')->nullable()->after('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_item_batches', function (Blueprint $table) {
            $table->dropIndex('inventory_item_batches_expiration_date_index');
            $table->dropIndex('inventory_item_batches_reference_no_index');
            $table->dropColumn(['unit_cost', 'expiration_date', 'source', 'reference_no', 'notes']);
        });
    }
};
