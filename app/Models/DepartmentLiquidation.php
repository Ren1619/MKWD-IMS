<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $supply_request_id
 * @property string $department
 * @property string $document_no
 * @property string $status
 * @property int $requested_by_user_id
 * @property Carbon $liquidation_date
 * @property list<array{supply_request_line_id?: int, inventory_item_id: int|null, item_description: string, unit_of_measure: string, quantity_issued: int, previously_utilized: int, quantity_utilized: int, balance: int, usage_details: string|null, source_stock_out_ids: list<int>}> $lines
 * @property list<array{action: string, actor_user_id: int, actor_name: string, at: string, remarks: string|null}> $history
 */
#[Fillable(['document_no', 'liquidation_date', 'department', 'active_department', 'supply_request_id', 'active_supply_request_id', 'isf_no', 'return_slip_no', 'return_slip_date', 'charge_code', 'requested_by_user_id', 'requested_by_name', 'status', 'lines', 'history'])]
class DepartmentLiquidation extends Model
{
    protected $attributes = ['status' => 'submitted'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['liquidation_date' => 'date', 'return_slip_date' => 'date', 'lines' => 'array', 'history' => 'array'];
    }

    /** @return BelongsTo<SupplyRequest, $this> */
    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class);
    }
}
