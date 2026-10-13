<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
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
            'series_category_id' => ['required', 'integer', Rule::exists('inv_series_cats', 'inv_series_cat_id')->where('is_active', true)],
            'accountable_reference_id' => ['nullable', 'integer', 'exists:hris_references,id'],
            'name' => ['required', 'string', 'max:255'],
            'stock_number' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+(?:[._\/-][A-Za-z0-9]+)*$/', 'unique:inventory_items,stock_number'],
            'unit_of_measure' => ['required', 'string', 'max:50'],
            'uacs_object_code' => ['nullable', 'string', 'max:50', 'regex:/^\d{8}-\d{2}$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'received_at' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'batch_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'disposed'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'series_category_id.required' => 'Select a major category, class category, and series category.',
            'name.required' => 'Enter the item name.',
            'unit_of_measure.required' => 'Enter the unit of measure.',
            'quantity.required' => 'Enter the opening quantity.',
            'reorder_point.required' => 'Enter the reorder point.',
            'unit_cost.required' => 'Enter the opening unit cost.',
            'status.required' => 'Select the item status.',
            'stock_number.regex' => 'The stock number may contain letters, numbers, periods, underscores, slashes, and hyphens, with no spaces.',
            'uacs_object_code.regex' => 'The UACS object code must use the format 00000000-00.',
        ];
    }
}
