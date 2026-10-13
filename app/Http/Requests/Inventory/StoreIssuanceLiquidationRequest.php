<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssuanceLiquidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['submit', 'check', 'approve'])],
            'attested' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'liquidation_date' => ['required_if:action,submit', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'lines' => ['required_if:action,submit', 'array', 'min:1'],
            'lines.*' => ['array:supply_request_line_id,quantity_utilized'],
            'lines.*.supply_request_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_utilized' => ['required', 'integer', 'min:0'],
        ];
    }
}
