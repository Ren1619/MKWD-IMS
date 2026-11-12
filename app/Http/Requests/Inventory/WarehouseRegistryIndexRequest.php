<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;

class WarehouseRegistryIndexRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
