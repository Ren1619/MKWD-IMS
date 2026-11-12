<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class RecordInventoryCountRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'latest_entry_id' => ['nullable', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(['good', 'defective'])],
            'location_type' => ['required', Rule::in(['stockroom', 'other'])],
            'location' => ['required', 'string', 'max:255', Rule::when($this->input('location_type') === 'stockroom', ['exists:warehouses,name'])],
            'other_location' => ['nullable', 'string', 'max:255'],
            'aisle' => ['nullable', 'string', 'max:255'],
            'bin' => ['nullable', 'string', 'max:255'],
            'counter_name' => ['required', 'string', 'max:255'],
            'checker_name' => ['required', 'string', 'max:255', 'different:counter_name'],
            'validator_name' => ['required', 'string', 'max:255', 'different:counter_name', 'different:checker_name'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'adjustment_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'valuation_basis' => ['nullable', 'string', 'max:3000'],
            'attested' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('location_type') === 'other') {
            $this->merge(['location' => $this->input('other_location')]);
        }
    }
}
