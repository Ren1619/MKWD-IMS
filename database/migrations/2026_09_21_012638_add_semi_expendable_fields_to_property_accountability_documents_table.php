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
        if (! Schema::hasColumn('property_accountability_documents', 'value_class')) {
            Schema::table('property_accountability_documents', function (Blueprint $table) {
                $table->string('value_class', 20)->nullable()->after('document_type');
            });
        }

        if (! Schema::hasColumn('property_accountability_documents', 'sequence_no')) {
            Schema::table('property_accountability_documents', function (Blueprint $table) {
                $table->unsignedInteger('sequence_no')->nullable()->after('value_class');
            });
        }

        if (! Schema::hasColumn('property_accountability_documents', 'accountability_expires_at')) {
            Schema::table('property_accountability_documents', function (Blueprint $table) {
                $table->date('accountability_expires_at')->nullable()->after('renewal_due_at');
            });
        }

        Schema::table('property_accountability_documents', function (Blueprint $table) {
            if (! Schema::hasIndex('property_accountability_documents', ['value_class'])) {
                $table->index('value_class', 'pa_docs_value_class_idx');
            }

            if (! Schema::hasIndex('property_accountability_documents', ['accountability_expires_at'])) {
                $table->index('accountability_expires_at', 'pa_docs_account_expiry_idx');
            }

            if (! Schema::hasIndex('property_accountability_documents', 'pa_docs_type_value_sequence_unique')) {
                $table->unique(['document_type', 'value_class', 'sequence_no', 'issued_at'], 'pa_docs_type_value_sequence_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_accountability_documents', function (Blueprint $table) {
            $table->dropUnique('pa_docs_type_value_sequence_unique');
            $table->dropColumn(['value_class', 'sequence_no', 'accountability_expires_at']);
        });
    }
};
