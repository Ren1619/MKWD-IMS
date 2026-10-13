<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['procurement_request_id' => ['required', 'integer', Rule::exists('procurement_requests', 'id')->whereNull('deleted_at')], 'idempotency_key' => ['required', 'uuid'],
            'supplier_name' => ['required', 'string', 'max:255'], 'invoice_number' => ['required', 'string', 'max:120'], 'delivery_reference' => ['required', 'string', 'max:120'],
            'received_at' => ['required', 'date_format:Y-m-d\\TH:i', 'before_or_equal:'.now('Asia/Manila')->format('Y-m-d\\TH:i')],
            'receiving_department' => ['required', 'string', 'max:255'], 'noa_reference' => ['nullable', 'string', 'max:120'], 'ntp_reference' => ['nullable', 'string', 'max:120'],
            'order_document_basis' => ['required', 'string', 'max:3000'], 'notification_reference' => ['required', 'string', 'max:255'], 'packaging_findings' => ['required', 'string', 'max:3000'], 'attested' => ['accepted'],
            'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*' => ['required', 'array:line_id,quantity_delivered,unit_cost,expiration_date'],
            'lines.*.line_id' => ['required', 'integer', 'distinct'], 'lines.*.quantity_delivered' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'], 'lines.*.expiration_date' => ['nullable', 'date_format:Y-m-d']];
    }
}
