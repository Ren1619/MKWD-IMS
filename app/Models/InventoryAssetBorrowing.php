<?php

namespace App\Models;

use Database\Factories\InventoryAssetBorrowingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $borrower_reference_id
 * @property string|null $borrower_name
 */
#[Fillable([
    'inventory_asset_id',
    'borrower_reference_id',
    'borrower_name',
    'status',
    'notes',
    'return_notes',
    'borrowed_at',
    'due_at',
    'returned_at',
])]
class InventoryAssetBorrowing extends Model
{
    /** @use HasFactory<InventoryAssetBorrowingFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_asset_borrowing_id';

    protected $attributes = [
        'status' => 'borrowed',
    ];

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id', 'inventory_asset_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function borrowerReference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'borrower_reference_id');
    }
}
