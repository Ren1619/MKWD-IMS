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
            $table->boolean('notation_required')->default(false);
            $table->string('recipient_department_id')->nullable();
            $table->string('recipient_division_id')->nullable();
            $table->string('recipient_division_name')->nullable();
            $table->foreignId('noted_by_user_id')->nullable()->constrained('users', indexName: 'pa_docs_noter_fk')->nullOnDelete();
            $table->string('noted_by_name')->nullable();
            $table->text('noter_attestation')->nullable();
            $table->timestamp('noted_at')->nullable();
            $table->index(['recipient_department_id', 'recipient_division_id', 'status'], 'pa_docs_notation_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_accountability_documents', function (Blueprint $table) {
            $table->dropForeign('pa_docs_noter_fk');
            $table->dropIndex('pa_docs_notation_scope_idx');
            $table->dropColumn(['notation_required', 'recipient_department_id', 'recipient_division_id', 'recipient_division_name', 'noted_by_user_id', 'noted_by_name', 'noter_attestation', 'noted_at']);
        });
    }
};
