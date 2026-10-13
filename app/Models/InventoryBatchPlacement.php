<?php

namespace App\Models;

use Database\Factories\InventoryBatchPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $version
 * @property int $observed_quantity
 * @property array<string,mixed> $source_details
 */
#[Fillable(['inventory_item_batch_id', 'version', 'warehouse', 'area', 'rack', 'bin', 'observed_quantity', 'source_evidence_reference', 'source_details', 'remarks', 'recorded_by_user_id', 'recorded_by_name'])]
class InventoryBatchPlacement extends Model
{
    /** @use HasFactory<InventoryBatchPlacementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['version' => 'integer', 'observed_quantity' => 'integer', 'source_details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Storage confirmations are immutable. Record a new placement.');
        });
        static::deleting(function (): void {
            throw new LogicException('Storage confirmations must remain available for audit.');
        });
    }

    /** @return BelongsTo<InventoryItemBatch,$this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryItemBatch::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }
}
