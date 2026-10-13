<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InventoryMonthlyReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $document_no
 * @property CarbonInterface $period_month
 * @property string $scope_key
 * @property int $revision
 * @property string $fund_cluster
 * @property int|null $series_category_id
 * @property string $classification_name
 * @property bool $include_zero
 * @property string $status
 * @property int $prepared_by_user_id
 * @property int $checked_by_user_id
 * @property int $noted_by_user_id
 * @property int $approved_by_user_id
 * @property array<string,array{user_id:int,name:string,role:string}> $signatories
 * @property string $reconciliation_reference
 * @property int|null $supersedes_id
 * @property string|null $correction_reason
 * @property int|null $active_snapshot_id
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $transmitted_at
 * @property string $request_hash
 */
#[Fillable(['document_no', 'period_month', 'scope_key', 'revision', 'fund_cluster', 'series_category_id', 'classification_name', 'include_zero', 'status', 'prepared_by_user_id', 'checked_by_user_id', 'noted_by_user_id', 'approved_by_user_id', 'signatories', 'reconciliation_reference', 'supersedes_id', 'correction_reason', 'active_snapshot_id', 'approved_at', 'transmitted_at', 'idempotency_key', 'request_hash'])]
class InventoryMonthlyReport extends Model
{
    /** @use HasFactory<InventoryMonthlyReportFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'prepared', 'include_zero' => true];

    protected function casts(): array
    {
        return ['period_month' => 'date', 'revision' => 'integer', 'include_zero' => 'boolean', 'signatories' => 'array', 'approved_at' => 'datetime', 'transmitted_at' => 'datetime'];
    }

    /** @return BelongsTo<InventoryMonthlyReportSnapshot,$this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(InventoryMonthlyReportSnapshot::class, 'active_snapshot_id');
    }

    /** @return HasMany<InventoryMonthlyReportSnapshot,$this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(InventoryMonthlyReportSnapshot::class);
    }

    /** @return HasMany<InventoryMonthlyReportAction,$this> */
    public function actions(): HasMany
    {
        return $this->hasMany(InventoryMonthlyReportAction::class);
    }

    /** @return BelongsTo<InventoryMonthlyReport,$this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
