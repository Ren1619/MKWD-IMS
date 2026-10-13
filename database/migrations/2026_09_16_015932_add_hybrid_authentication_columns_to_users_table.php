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
            $table->boolean('local_fallback_enabled')->default(true)->after('is_active');
            $table->timestamp('last_hris_authenticated_at')->nullable()->after('last_login_at');
            $table->timestamp('fallback_valid_until')->nullable()->after('last_hris_authenticated_at');
            $table->timestamp('last_fallback_authenticated_at')->nullable()->after('fallback_valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'local_fallback_enabled',
                'last_hris_authenticated_at',
                'fallback_valid_until',
                'last_fallback_authenticated_at',
            ]);
        });
    }
};
