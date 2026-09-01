<?php

namespace App\Models;

use Database\Factories\SupplyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ris_no', 'requester_user_id', 'requester_reference_id', 'requester_name', 'office_name', 'responsibility_center_code', 'purpose', 'date_needed', 'status', 'submitted_at', 'completed_at'])]
class SupplyRequest extends Model
{
    /** @use HasFactory<SupplyRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['date_needed' => 'date', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
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
}
