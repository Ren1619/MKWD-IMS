<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique(Warehouse::class, 'name')->ignore($this->route('warehouse'))],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => 'Enter a stockroom name.', 'name.unique' => 'This stockroom name is already registered.'];
    }
}
