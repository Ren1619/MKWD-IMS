<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_count_line_id
 * @property int $recorded_by_user_id
 * @property int $quantity
 * @property int|null $observed_quantity
 * @property int $after_count_issues_at_observation
 * @property string|null $condition
 * @property string|null $location
 * @property string|null $aisle
 * @property string|null $bin
 * @property string|null $counter_name
 * @property string|null $checker_name
 * @property string|null $validator_name
 * @property string|null $remarks
 */
#[Fillable(['inventory_count_line_id', 'recorded_by_user_id', 'quantity', 'observed_quantity', 'after_count_issues_at_observation', 'condition', 'location', 'aisle', 'bin', 'counter_name', 'checker_name', 'validator_name', 'remarks'])]
class InventoryCountEntry extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Physical count evidence is immutable. Record a new observation or action.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Physical count evidence cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'observed_quantity' => 'integer', 'after_count_issues_at_observation' => 'integer'];
    }

    /** @return BelongsTo<InventoryCountLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(InventoryCountLine::class, 'inventory_count_line_id');
    }
}
