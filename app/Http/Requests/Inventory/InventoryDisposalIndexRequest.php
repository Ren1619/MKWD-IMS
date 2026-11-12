<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class InventoryDisposalIndexRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['submitted', 'approved', 'completed', 'rejected', 'cancelled'])]];
    }
}
