<?php

namespace App\Services;

use App\Models\InventoryMonthlyReport;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryMonthlyReportService
{
    public function __construct(private InventoryStockGuard $guard, private InventoryMonthlyReportBuilder $builder, private AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function create(array $data, User $actor): InventoryMonthlyReport
    {
        return DB::transaction(function () use ($data, $actor): InventoryMonthlyReport {
            $this->guard->lock();
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = InventoryMonthlyReport::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                if ($existing->prepared_by_user_id !== $actor->id || $existing->request_hash !== $hash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission key belongs to a different monthly report.']);
                }

                return $existing;
            }
            $categoryId = isset($data['series_category_id']) ? (int) $data['series_category_id'] : null;
            $scope = hash('sha256', json_encode([$data['fund_cluster'], $categoryId, (bool) $data['include_zero']], JSON_THROW_ON_ERROR));
            $month = Carbon::createFromFormat('!Y-m', $data['period_month'], 'Asia/Manila')->toDateString();
            $sameScope = InventoryMonthlyReport::query()->whereDate('period_month', $month)->where('scope_key', $scope)->orderByDesc('revision')->get();
            if ($sameScope->contains(fn ($record): bool => in_array($record->status, ['prepared', 'checked', 'noted'], true))) {
                throw ValidationException::withMessages(['period_month' => 'A package for this month and selection already awaits sign-off. Refresh or cancel that package first.']);
            }
            $prior = $sameScope->first(fn ($record): bool => in_array($record->status, ['approved', 'transmitted'], true));
            if ($prior !== null && blank($data['correction_reason'] ?? null)) {
                throw ValidationException::withMessages(['correction_reason' => 'Explain why a correction revision is needed; the earlier approved package remains preserved.']);
            }
            $ids = [$actor->id, (int) $data['checked_by_user_id'], (int) $data['noted_by_user_id'], (int) $data['approved_by_user_id']];
            if (count(array_unique($ids)) !== 4) {
                throw ValidationException::withMessages(['checked_by_user_id' => 'Assign four distinct officers for preparation, checking, noting, and approval.']);
            }
            $users = User::query()->whereIn('id', $ids)->where('is_active', true)->whereIn('role', ['inventory_manager', 'super_admin'])->get()->keyBy('id');
            if ($users->count() !== 4) {
                throw ValidationException::withMessages(['checked_by_user_id' => 'All signatories must have active inventory authorization.']);
            }
            $signatories = [];
            foreach (['prepared' => ['Prepared by / Senior Supply Officer or PPE Custodian', $actor->id], 'checked' => ['Checked by / PMMD Division Manager', (int) $data['checked_by_user_id']], 'noted' => ['Noted by / AHRD Department Manager', (int) $data['noted_by_user_id']], 'approved' => ['Approved by / AGM for Admin and Finance', (int) $data['approved_by_user_id']]] as $key => [$role,$id]) {
                $signatories[$key] = ['user_id' => $id, 'name' => $users[$id]->name, 'role' => $role];
            }
            $built = $this->builder->build($data['period_month'], $categoryId, (bool) $data['include_zero'], $data['fund_cluster']);
            $this->assertReady($built['readiness']['blockers']);
            $document = InventoryMonthlyReport::query()->create([
                ...collect($data)->only(['fund_cluster', 'include_zero', 'checked_by_user_id', 'noted_by_user_id', 'approved_by_user_id', 'reconciliation_reference', 'correction_reason', 'idempotency_key'])->all(),
                'document_no' => 'TMP-'.Str::uuid(), 'period_month' => $month, 'scope_key' => $scope, 'revision' => (int) $sameScope->max('revision') + 1,
                'series_category_id' => $categoryId, 'classification_name' => $categoryId === null ? 'All inventory classifications' : InventorySeriesCategory::query()->findOrFail($categoryId)->name,
                'prepared_by_user_id' => $actor->id, 'signatories' => $signatories, 'supersedes_id' => $prior?->id, 'request_hash' => $hash]);
            $document->update(['document_no' => sprintf('MR-%s-%06d-R%d', str_replace('-', '', $data['period_month']), $document->id, $document->revision)]);
            $snapshot = $document->snapshots()->create(['version' => 1, 'source_hash' => $built['hash'], 'payload' => $built['payload'], 'readiness' => $built['readiness'], 'generated_by_user_id' => $actor->id, 'generated_by_name' => $actor->name]);
            $document->update(['active_snapshot_id' => $snapshot->id]);
            $this->record($document, $actor, 'prepare', null, $data['reconciliation_reference'], ['correction_reason' => $data['correction_reason'] ?? null]);

            return $document;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $data */
    public function transition(InventoryMonthlyReport $document, array $data, User $actor): void
    {
        DB::transaction(function () use ($document, $data, $actor): void {
            $this->guard->lock();
            $document = InventoryMonthlyReport::query()->whereKey($document->id)->lockForUpdate()->with('snapshot')->firstOrFail();
            if ($document->active_snapshot_id !== (int) $data['snapshot_id']) {
                throw ValidationException::withMessages(['snapshot_id' => 'This report snapshot has changed. Reload it before making a decision.']);
            }
            if (! in_array($data['action'], $this->allowedActions($document, $actor), true)) {
                throw ValidationException::withMessages(['action' => 'This action requires the assigned signatory and the correct workflow stage.']);
            }
            $from = $document->status;
            $action = $data['action'];
            if (in_array($action, ['check', 'note', 'approve', 'refresh'], true)) {
                $built = $this->builder->build($document->period_month->format('Y-m'), $document->series_category_id, $document->include_zero, $document->fund_cluster);
                $this->assertReady($built['readiness']['blockers']);
                if ($action === 'refresh') {
                    $version = (int) $document->snapshots()->max('version') + 1;
                    $snapshot = $document->snapshots()->create(['version' => $version, 'source_hash' => $built['hash'], 'payload' => $built['payload'], 'readiness' => $built['readiness'], 'generated_by_user_id' => $actor->id, 'generated_by_name' => $actor->name]);
                    $document->update(['active_snapshot_id' => $snapshot->id, 'status' => 'prepared', 'reconciliation_reference' => $data['remarks']]);
                } else {
                    if ($document->snapshot->source_hash !== $built['hash']) {
                        throw ValidationException::withMessages(['snapshot_id' => 'Source transactions changed since preparation. The preparer must refresh the snapshot and repeat sign-off.']);
                    }
                    $document->update(['status' => match ($action) {
                        'check' => 'checked','note' => 'noted',default => 'approved'
                    }, 'approved_at' => $action === 'approve' ? now() : null]);
                }
            } elseif ($action === 'cancel') {
                $document->update(['status' => 'cancelled']);
            } else {
                $document->update(['status' => 'transmitted', 'transmitted_at' => now()]);
            }
            $this->record($document, $actor, $action, $from, $data['remarks'], collect($data)->except(['action', 'remarks', 'attested', 'snapshot_id'])->all());
        }, attempts: 3);
    }

    /** @return list<string> */
    public function allowedActions(InventoryMonthlyReport $document, User $actor): array
    {
        if (! $actor->is_active || ! $actor->can('manage-inventory')) {
            return [];
        }
        $actions = [];
        $required = match ($document->status) {
            'prepared' => ['check', $document->checked_by_user_id],'checked' => ['note', $document->noted_by_user_id],'noted' => ['approve', $document->approved_by_user_id],default => null
        };
        if ($required !== null && $required[1] === $actor->id) {
            $actions[] = $required[0];
        }
        if (in_array($document->status, ['prepared', 'checked', 'noted'], true) && $document->prepared_by_user_id === $actor->id) {
            $actions[] = 'refresh';
            $actions[] = 'cancel';
        }
        if ($document->status === 'approved' && in_array($actor->id, [$document->prepared_by_user_id, $document->approved_by_user_id], true)) {
            $actions[] = 'transmit';
        }

        return $actions;
    }

    /** @param list<array<string,mixed>> $blockers */
    private function assertReady(array $blockers): void
    {
        if ($blockers !== []) {
            $examples = collect($blockers)->take(5)->map(fn ($row): string => $row['kind'].' '.$row['reference'])->implode('; ');
            throw ValidationException::withMessages(['reconciliation' => 'Resolve '.count($blockers).' outstanding approval or reconciliation issue(s): '.$examples.'.']);
        }
    }

    /** @param array<string,mixed> $metadata */
    private function record(InventoryMonthlyReport $document, User $actor, string $action, ?string $from, string $remarks, array $metadata): void
    {
        $document->actions()->create(['inventory_monthly_report_snapshot_id' => $document->active_snapshot_id, 'actor_user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'from_status' => $from, 'to_status' => $document->status, 'remarks' => $remarks, 'metadata' => $metadata]);
        $this->audit->record('inventory.monthly_report.'.$action, $document->document_no.': '.$action, $document, newValues: $metadata, actor: $actor);
    }
}
