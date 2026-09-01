<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionProcurementRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['submit', 'budget_review', 'approve', 'forward', 'order', 'record_delivery', 'accept', 'reject', 'cancel'])],
            'attested' => ['accepted'], 'remarks' => ['nullable', 'string', 'max:2000'], 'procurement_mode' => ['nullable', 'string', 'max:100'],
            'purchase_order_no' => ['nullable', 'string', 'max:100'], 'inspection_acceptance_no' => ['nullable', 'string', 'max:100'],
            'delivery_reference' => ['nullable', 'string', 'max:100'], 'received_at' => ['nullable', 'date'],
            'actual_unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
