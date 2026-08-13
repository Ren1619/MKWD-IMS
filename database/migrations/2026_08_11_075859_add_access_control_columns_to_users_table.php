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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hris_reference_id')->nullable()->after('id')
                ->unique()
                ->constrained('hris_references')
                ->nullOnDelete();
            $table->string('role', 30)->default('inventory_user')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->index(['role', 'is_active'], 'users_role_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_active_idx');
            $table->dropConstrainedForeignId('hris_reference_id');
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
