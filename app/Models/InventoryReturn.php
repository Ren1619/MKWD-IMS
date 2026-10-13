<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InventoryReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $document_no
 * @property CarbonInterface $document_date
 * @property int $daily_number
 * @property CarbonInterface $return_date
 * @property string $source_mode
 * @property int|null $returned_by_reference_id
 * @property string $returned_by_name
 * @property string $department_name
 * @property string $endorsement_reference
 * @property string|null $legacy_issue_reference
 * @property string $reason
 * @property string $status
 * @property int $created_by_user_id
 * @property int|null $verified_by_user_id
 * @property int|null $approved_by_user_id
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string|null $inspection_reference
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $accounting_endorsed_at
 */
#[Fillable(['document_no', 'document_date', 'daily_number', 'return_date', 'source_mode', 'returned_by_reference_id', 'returned_by_name', 'department_name', 'endorsement_reference', 'legacy_issue_reference', 'reason', 'status', 'created_by_user_id', 'verified_by_user_id', 'approved_by_user_id', 'idempotency_key', 'request_hash', 'inspection_reference', 'approved_at', 'accounting_endorsed_at'])]
class InventoryReturn extends Model
{
    /** @use HasFactory<InventoryReturnFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'return_date' => 'date', 'daily_number' => 'integer', 'approved_at' => 'datetime', 'accounting_endorsed_at' => 'datetime'];
    }

    /** @return HasMany<InventoryReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryReturnLine::class);
    }

    /** @return HasMany<InventoryReturnAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(InventoryReturnAction::class);
    }
}
