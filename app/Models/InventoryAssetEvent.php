<?php

namespace App\Models;

use App\AssetConditionStatus;
use App\InventoryAssetEventType;
use Carbon\CarbonInterface;
use Database\Factories\InventoryAssetEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property InventoryAssetEventType $event_type
 * @property CarbonInterface $event_date
 * @property array<string,mixed>|null $details
 * @property int|null $quantity
 * @property string|null $amount
 * @property AssetConditionStatus|null $condition_status
 */
#[Fillable([
    'inventory_asset_id',
    'event_type',
    'event_date',
    'reference_no',
    'amount',
    'quantity',
    'from_location',
    'to_location',
    'condition_status',
    'notes',
    'details',
    'idempotency_key',
    'recorded_by_user_id',
])]
class InventoryAssetEvent extends Model
{
    /** @use HasFactory<InventoryAssetEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_type' => InventoryAssetEventType::class,
            'event_date' => 'date',
            'amount' => 'decimal:2',
            'quantity' => 'integer',
            'condition_status' => AssetConditionStatus::class,
            'details' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Property lifecycle events are immutable. Record a correcting event instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Property lifecycle events cannot be deleted.');
        });
    }

    /** @return BelongsTo<InventoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id', 'inventory_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
