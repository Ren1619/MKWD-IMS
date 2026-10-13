<?php

namespace App\Models;

use Database\Factories\ProcurementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property array<string, mixed>|null $preprocurement */
#[Fillable(['pr_no', 'supply_request_id', 'created_by_user_id', 'type', 'source', 'status', 'purpose', 'funding_source', 'responsibility_center_code', 'ppmp_reference', 'app_reference', 'app_cse_classification', 'procurement_mode', 'purchase_order_no', 'inspection_acceptance_no', 'required_at', 'completed_at', 'document_kind', 'procurement_category', 'preprocurement'])]
class ProcurementRequest extends Model
{
    /** @use HasFactory<ProcurementRequestFactory> */
    use HasFactory, SoftDeletes;

    public function canDelete(): bool
    {
        return ! $this->trashed() && in_array($this->status, ['intake', 'draft'], true) && $this->supply_request_id === null && ($this->relationLoaded('attachments') ? $this->attachments->isEmpty() : $this->attachments()->doesntExist());
    }

    public function canArchive(): bool
    {
        return ! $this->trashed() && in_array($this->status, ['accepted', 'endorsed', 'rejected', 'cancelled'], true);
    }

    protected $attributes = ['document_kind' => 'PR'];

    /** @return HasMany<ProcurementAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProcurementAttachment::class);
    }

    protected function casts(): array
    {
        return ['required_at' => 'date', 'completed_at' => 'datetime', 'preprocurement' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<SupplyRequest, $this> */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }

    /** @return HasMany<ProcurementRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementRequestLine::class);
    }

    /** @return HasMany<ProcurementDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ProcurementDelivery::class);
    }

    /** @return HasMany<ProcurementRequestAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ProcurementRequestAction::class)->latest();
    }
}
