<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
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
            'series_category_id' => ['required', 'integer', 'exists:inv_series_cats,inv_series_cat_id'],
            'accountable_reference_id' => ['nullable', 'integer', 'exists:hris_references,id'],
            'name' => ['required', 'string', 'max:255'],
            'stock_number' => ['nullable', 'string', 'max:100', Rule::unique('inventory_items', 'stock_number')->ignore($this->route('item'))],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'uacs_object_code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'disposed'])],
        ];
    }
}
