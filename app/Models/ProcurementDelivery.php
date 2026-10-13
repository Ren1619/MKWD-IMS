<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProcurementDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $procurement_request_id
 * @property string $document_no
 * @property string $purchase_order_no
 * @property string $supplier_name
 * @property string $invoice_number
 * @property string $delivery_reference
 * @property CarbonInterface $received_at
 * @property string $receiving_department
 * @property string $status
 * @property int $created_by_user_id
 * @property int|null $inspected_by_user_id
 * @property int|null $accepted_by_user_id
 * @property string|null $inspection_acceptance_no
 * @property string|null $receiving_report_no
 * @property string|null $supplier_return_no
 * @property array<string,mixed>|null $inspection
 * @property CarbonInterface|null $accepted_at
 * @property CarbonInterface|null $supplier_returned_at
 * @property CarbonInterface|null $endorsed_at
 * @property string $request_hash
 */
#[Fillable(['procurement_request_id', 'document_no', 'purchase_order_no', 'supplier_name', 'invoice_number', 'delivery_reference', 'received_at', 'receiving_department', 'noa_reference', 'ntp_reference', 'order_document_basis', 'notification_reference', 'packaging_findings', 'status', 'created_by_user_id', 'inspected_by_user_id', 'accepted_by_user_id', 'inspection_acceptance_no', 'receiving_report_no', 'supplier_return_no', 'inspection', 'accepted_at', 'supplier_returned_at', 'endorsed_at', 'idempotency_key', 'request_hash'])]
class ProcurementDelivery extends Model
{
    /** @use HasFactory<ProcurementDeliveryFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'received'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'accepted_at' => 'datetime', 'supplier_returned_at' => 'datetime', 'endorsed_at' => 'datetime', 'inspection' => 'array'];
    }

    /** @return BelongsTo<ProcurementRequest,$this> */
    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class)->withTrashed();
    }

    /** @return HasMany<ProcurementDeliveryLine,$this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProcurementDeliveryLine::class);
    }

    /** @return HasMany<ProcurementDeliveryAction,$this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ProcurementDeliveryAction::class);
    }
}
