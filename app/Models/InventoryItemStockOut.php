<?php

namespace App\Models;

use Database\Factories\InventoryItemStockOutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inventory_item_id',
    'recipient_reference_id',
    'recipient_name',
    'ris_no',
    'responsibility_center_code',
    'quantity',
    'stocked_out_at',
    'notes',
])]
class InventoryItemStockOut extends Model
{
    /** @use HasFactory<InventoryItemStockOutFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_item_stock_out_id';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stocked_out_at' => 'date',
        ];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function recipientReference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'recipient_reference_id');
    }
}
