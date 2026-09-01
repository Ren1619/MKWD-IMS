<?php

namespace App\Models;

use Database\Factories\ProcurementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pr_no', 'supply_request_id', 'created_by_user_id', 'type', 'source', 'status', 'purpose', 'funding_source', 'responsibility_center_code', 'ppmp_reference', 'app_reference', 'app_cse_classification', 'procurement_mode', 'purchase_order_no', 'inspection_acceptance_no', 'required_at', 'completed_at'])]
class ProcurementRequest extends Model
{
    /** @use HasFactory<ProcurementRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['required_at' => 'date', 'completed_at' => 'datetime'];
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

    /** @return HasMany<ProcurementRequestAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ProcurementRequestAction::class)->latest();
    }
}
