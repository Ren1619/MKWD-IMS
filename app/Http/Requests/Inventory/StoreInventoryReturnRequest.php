<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'source_mode' => ['required', Rule::in(['linked', 'legacy'])],
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'department_name' => ['required', 'string', 'max:255'],
            'returned_by_reference_id' => ['nullable', 'integer', Rule::exists('hris_references', 'id')->where('type', 'employee')->where('is_active', true)],
            'returned_by_name' => [Rule::requiredIf($this->input('source_mode') === 'legacy' && ! $this->filled('returned_by_reference_id')), 'nullable', 'string', 'max:255'],
            'endorsement_reference' => ['required', 'string', 'max:120'],
            'legacy_issue_reference' => ['required_if:source_mode,legacy', 'nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:3000'],
            'attested' => ['accepted'],
            'lines' => ['required', 'array', 'min:1', 'max:25'],
            'lines.*' => ['required', 'array:source_allocation_id,inventory_item_id,quantity,unit_cost,valuation_basis,expiration_date'],
            'lines.*.source_allocation_id' => ['required_if:source_mode,linked', 'prohibited_if:source_mode,legacy', 'nullable', 'integer', 'distinct', Rule::exists('inventory_item_stock_out_allocations', 'inventory_item_stock_out_allocation_id')],
            'lines.*.inventory_item_id' => ['required_if:source_mode,legacy', 'prohibited_if:source_mode,linked', 'nullable', 'integer', 'distinct', Rule::exists('inventory_items', 'inventory_item_id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_cost' => ['required_if:source_mode,legacy', 'prohibited_if:source_mode,linked', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'lines.*.valuation_basis' => ['required_if:source_mode,legacy', 'prohibited_if:source_mode,linked', 'nullable', 'string', 'max:3000'],
            'lines.*.expiration_date' => ['prohibited_if:source_mode,linked', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
