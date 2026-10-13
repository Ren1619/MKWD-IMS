<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $procurement_delivery_id
 * @property int $procurement_request_line_id
 * @property string $item_name
 * @property string $unit_of_measure
 * @property string|null $specifications
 * @property int $quantity_delivered
 * @property int|null $quantity_accepted
 * @property int $quantity_committed
 * @property string $unit_cost
 * @property CarbonInterface|null $expiration_date
 * @property string|null $condition
 * @property string|null $disposition
 * @property string|null $storage_location
 * @property string|null $disposition_reference
 * @property string|null $findings
 * @property int|null $inventory_item_batch_id
 * @property list<int>|null $asset_ids
 */
#[Fillable(['procurement_delivery_id', 'procurement_request_line_id', 'item_name', 'unit_of_measure', 'specifications', 'quantity_delivered', 'quantity_accepted', 'quantity_committed', 'unit_cost', 'expiration_date', 'condition', 'disposition', 'storage_location', 'disposition_reference', 'findings', 'inventory_item_batch_id', 'asset_ids'])]
class ProcurementDeliveryLine extends Model
{
    protected function casts(): array
    {
        return ['quantity_delivered' => 'integer', 'quantity_accepted' => 'integer', 'quantity_committed' => 'integer', 'unit_cost' => 'decimal:2', 'expiration_date' => 'date', 'asset_ids' => 'array'];
    }

    /** @return BelongsTo<ProcurementRequestLine,$this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestLine::class, 'procurement_request_line_id');
    }

    /** @return BelongsTo<ProcurementDelivery,$this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(ProcurementDelivery::class, 'procurement_delivery_id');
    }

    /** @return BelongsTo<InventoryItemBatch,$this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryItemBatch::class, 'inventory_item_batch_id', 'inventory_item_batch_id');
    }
}
