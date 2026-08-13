<?php

namespace App\Models;

use Database\Factories\InventoryAssetCustodianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_asset_id', 'hris_reference_id', 'assigned_at', 'unassigned_at'])]
class InventoryAssetCustodian extends Model
{
    /** @use HasFactory<InventoryAssetCustodianFactory> */
    use HasFactory;

    protected $primaryKey = 'inventory_asset_custodian_id';

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id', 'inventory_asset_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function reference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'hris_reference_id');
    }
}
