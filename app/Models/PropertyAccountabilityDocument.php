<?php

namespace App\Models;

use Database\Factories\PropertyAccountabilityDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_no', 'document_type', 'inventory_asset_id', 'inventory_asset_custodian_id',
    'recipient_reference_id', 'issued_by_user_id', 'acknowledged_by_user_id', 'supersedes_document_id',
    'status', 'entity_name', 'fund_cluster', 'asset_name', 'asset_description', 'property_number',
    'serial_number', 'unit_of_measure', 'quantity', 'acquisition_date', 'acquisition_cost',
    'estimated_useful_life_months', 'recipient_name', 'recipient_code', 'recipient_position',
    'issued_by_name', 'issuer_attestation', 'recipient_attestation', 'issued_at', 'acknowledged_at',
    'renewal_due_at', 'closed_at', 'closure_reason',
])]
class PropertyAccountabilityDocument extends Model
{
    public const CAPITALIZATION_THRESHOLD = 50000;

    /** @use HasFactory<PropertyAccountabilityDocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'estimated_useful_life_months' => 'integer',
            'issued_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'renewal_due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InventoryAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'inventory_asset_id', 'inventory_asset_id');
    }

    /** @return BelongsTo<InventoryAssetCustodian, $this> */
    public function custodianAssignment(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCustodian::class, 'inventory_asset_custodian_id', 'inventory_asset_custodian_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function recipientReference(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'recipient_reference_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    /** @return BelongsTo<PropertyAccountabilityDocument, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_document_id');
    }

    /** @return HasMany<PropertyAccountabilityAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(PropertyAccountabilityAction::class)->latest();
    }
}
