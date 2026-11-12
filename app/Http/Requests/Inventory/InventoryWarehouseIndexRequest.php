<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;

class InventoryWarehouseIndexRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'], 'warehouse' => ['nullable', 'string', 'max:150'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'placement' => ['nullable', 'in:unplaced,placed'], 'include_depleted' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
