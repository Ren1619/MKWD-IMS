<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class InventoryWarehouseIndexRequest extends FormRequest
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
            'placement' => ['nullable', 'in:unplaced,placed'], 'include_depleted' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
