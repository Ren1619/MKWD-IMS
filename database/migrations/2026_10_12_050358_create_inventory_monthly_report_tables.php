<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_monthly_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no')->unique();
            $table->date('period_month')->index();
            $table->string('scope_key', 64);
            $table->unsignedInteger('revision');
            $table->string('fund_cluster', 50);
            $table->foreignId('series_category_id')->nullable()->constrained('inv_series_cats', 'inv_series_cat_id', indexName: 'monthly_category_fk')->restrictOnDelete();
            $table->string('classification_name');
            $table->boolean('include_zero')->default(true);
            $table->string('status')->default('prepared')->index();
            $table->foreignId('prepared_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('noted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->json('signatories');
            $table->text('reconciliation_reference');
            $table->foreignId('supersedes_id')->nullable()->constrained('inventory_monthly_reports')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->unsignedBigInteger('active_snapshot_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('transmitted_at')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->timestamps();
            $table->unique(['period_month', 'scope_key', 'revision'], 'monthly_scope_revision_unique');
        });
        Schema::create('inventory_monthly_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_monthly_report_id')->constrained(indexName: 'monthly_snapshot_packet_fk')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('source_hash', 64);
            $table->json('payload');
            $table->json('readiness');
            $table->foreignId('generated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('generated_by_name');
            $table->timestamps();
            $table->unique(['inventory_monthly_report_id', 'version'], 'monthly_snapshot_version_unique');
        });
        Schema::table('inventory_monthly_reports', function (Blueprint $table): void {
            $table->foreign('active_snapshot_id', 'monthly_active_snapshot_fk')->references('id')->on('inventory_monthly_report_snapshots')->restrictOnDelete();
        });
        Schema::create('inventory_monthly_report_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_monthly_report_id')->constrained(indexName: 'monthly_action_packet_fk')->restrictOnDelete();
            $table->foreignId('inventory_monthly_report_snapshot_id')->constrained(indexName: 'monthly_action_snapshot_fk')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('remarks');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_monthly_report_actions');
        Schema::table('inventory_monthly_reports', fn (Blueprint $table) => $table->dropForeign('monthly_active_snapshot_fk'));
        Schema::dropIfExists('inventory_monthly_report_snapshots');
        Schema::dropIfExists('inventory_monthly_reports');
    }
};
