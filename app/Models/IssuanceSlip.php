<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slip_no
 * @property string $status
 * @property Carbon $issued_at
 * @property Carbon|null $liquidation_date
 * @property array{department: string|null, charge_code: string|null, requested_by: string, isf_no: string|null, requester_user_id: int, requester_reference_id: int|null} $snapshot
 * @property list<array{supply_request_line_id: int, item_description: string, unit_of_measure: string, quantity_issued: int, quantity_utilized?: int, balance?: int}> $lines
 * @property list<array{action: string, actor_user_id: int, actor_name: string, at: string, remarks: string|null, attestation: string}> $history
 */
#[Fillable(['supply_request_id', 'slip_no', 'issued_at', 'issued_by_user_id', 'snapshot', 'lines', 'status', 'liquidation_date', 'history'])]
class IssuanceSlip extends Model
{
    protected $attributes = ['status' => 'pending', 'history' => '[]'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issued_at' => 'date', 'liquidation_date' => 'date', 'snapshot' => 'array', 'lines' => 'array', 'history' => 'array'];
    }

    /** @return BelongsTo<SupplyRequest, $this> */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }
}
