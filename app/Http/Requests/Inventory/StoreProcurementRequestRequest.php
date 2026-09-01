<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcurementRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        return [
            'supply_request_id' => ['nullable', 'exists:supply_requests,id'], 'type' => ['required', Rule::in(['replenishment', 'new_item', 'mixed'])],
            'source' => ['required', Rule::in(['manual', 'low_stock', 'request_shortage'])], 'purpose' => ['required', 'string', 'max:2000'],
            'funding_source' => ['nullable', 'string', 'max:150'], 'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'ppmp_reference' => ['nullable', 'string', 'max:100'], 'app_reference' => ['nullable', 'string', 'max:100'],
            'app_cse_classification' => ['nullable', 'string', 'max:100'], 'required_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1', 'max:50'], 'lines.*.inventory_item_id' => ['nullable', 'exists:inventory_items,inventory_item_id'],
            'lines.*.series_category_id' => ['nullable', 'exists:inv_series_cats,inv_series_cat_id'],
            'lines.*.item_name' => ['required', 'string', 'max:255'], 'lines.*.specifications' => ['nullable', 'string', 'max:2000'],
            'lines.*.unit_of_measure' => ['required', 'string', 'max:50'], 'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.estimated_unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
