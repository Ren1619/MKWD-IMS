<?php

namespace App\Models;

use Database\Factories\SupplyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon|null $return_slip_date */
#[Fillable(['ris_no', 'isf_no', 'isf_date', 'isf_sequence', 'issuance_details', 'return_slip_no', 'return_slip_date', 'requester_user_id', 'requester_reference_id', 'requester_name', 'office_name', 'responsibility_center_code', 'purpose', 'date_needed', 'status', 'submitted_at', 'completed_at'])]
class SupplyRequest extends Model
{
    /** @use HasFactory<SupplyRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['isf_date' => 'date', 'return_slip_date' => 'date', 'isf_sequence' => 'integer', 'issuance_details' => 'array', 'date_needed' => 'date', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return HasMany<SupplyRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplyRequestLine::class);
    }

    /** @return HasMany<SupplyRequestAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(SupplyRequestAction::class)->latest();
    }

    /** @return HasMany<IssuanceSlip, $this> */
    public function issuanceSlips(): HasMany
    {
        return $this->hasMany(IssuanceSlip::class);
    }

    /** @return HasMany<ProcurementRequest, $this> */
    public function procurementRequests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class);
    }
}
