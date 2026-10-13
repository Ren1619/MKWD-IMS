<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InventoryPropertyTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $source_snapshot
 * @property array<string, mixed> $recipient_snapshot
 * @property CarbonInterface $desired_date
 * @property CarbonInterface|null $completed_at
 */
#[Fillable(['document_no', 'inventory_asset_id', 'source_document_id', 'recipient_reference_id', 'transfer_type', 'reason', 'destination_location', 'desired_date', 'status', 'source_snapshot', 'recipient_snapshot', 'replacement_justification', 'created_by_user_id', 'verified_by_user_id', 'approved_by_user_id', 'replacement_document_id', 'completed_at'])]
class InventoryPropertyTransfer extends Model
{
    /** @use HasFactory<InventoryPropertyTransferFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'recipient_snapshot' => 'array', 'desired_date' => 'date', 'completed_at' => 'datetime'];
    }

    /** @return HasMany<InventoryPropertyTransferAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(InventoryPropertyTransferAction::class)->orderBy('id');
    }

    /** @return BelongsTo<PropertyAccountabilityDocument, $this> */
    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(PropertyAccountabilityDocument::class, 'replacement_document_id');
    }

    public function isExternal(): bool
    {
        return in_array($this->transfer_type, ['agency_transfer', 'donation'], true);
    }

    protected static function booted(): void
    {
        static::updating(function (self $transfer): void {
            if ($transfer->getOriginal('document_no') !== null && $transfer->isDirty('document_no')) {
                throw new \LogicException('The PTR number is permanent.');
            }
            if ($transfer->isDirty(['inventory_asset_id', 'source_document_id', 'recipient_reference_id', 'transfer_type', 'reason', 'destination_location', 'desired_date', 'source_snapshot', 'recipient_snapshot', 'replacement_justification', 'created_by_user_id'])) {
                throw new \LogicException('Submitted transfer details are frozen. Cancel and prepare a corrected request.');
            }
        });
        static::deleting(function (): never {
            throw new \LogicException('Transfer records cannot be deleted.');
        });
    }
}
