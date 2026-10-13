<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\InventoryDisposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $certifier_snapshot
 * @property CarbonInterface|null $completed_at
 */
#[Fillable(['document_no', 'place_of_storage', 'method', 'reason', 'status', 'created_by_user_id', 'certifier_snapshot', 'certification_reference', 'certification_attestation', 'completed_at'])]
class InventoryDisposal extends Model
{
    /** @use HasFactory<InventoryDisposalFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['certifier_snapshot' => 'array', 'completed_at' => 'datetime'];
    }

    /** @return HasMany<InventoryDisposalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryDisposalLine::class)->orderBy('id');
    }

    /** @return HasMany<InventoryDisposalAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(InventoryDisposalAction::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->isDirty(['place_of_storage', 'method', 'reason', 'created_by_user_id', 'certifier_snapshot', 'certification_reference', 'certification_attestation']) || ($document->getOriginal('document_no') !== null && $document->isDirty('document_no'))) {
                throw new \LogicException('Submitted WMR details are frozen. Cancel and prepare a corrected report.');
            }
        });
        static::deleting(function (): never {
            throw new \LogicException('Disposal records cannot be deleted.');
        });
    }
}
