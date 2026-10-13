<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentLiquidationRequest extends FormRequest
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
            'supply_request_id' => ['required', 'integer', 'exists:supply_requests,id'],
            'liquidation_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'return_slip_no' => ['nullable', 'string', 'max:100', 'required_with:return_slip_date'],
            'return_slip_date' => ['nullable', 'date_format:Y-m-d', 'required_with:return_slip_no', 'before_or_equal:liquidation_date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'attested' => ['accepted'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['array:supply_request_line_id,quantity_utilized,usage_details'],
            'lines.*.supply_request_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_utilized' => ['required', 'integer', 'min:0'],
            'lines.*.usage_details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
