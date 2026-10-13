<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcurementDeliveryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['received', 'inspected', 'accepted', 'rejected', 'cancelled'])], 'procurement_request_id' => ['nullable', 'integer', Rule::exists('procurement_requests', 'id')], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
