<?php

namespace App\Models;

use Database\Factories\InventoryItemBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_item_id', 'batch_number', 'quantity_in', 'quantity_remaining', 'received_at'])]
class InventoryItemBatch extends Model
{
    /** @use HasFactory<InventoryItemBatchFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_batch_id';

    protected function casts(): array
    {
        return [
            'quantity_in' => 'integer',
            'quantity_remaining' => 'integer',
            'received_at' => 'date',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }
}
