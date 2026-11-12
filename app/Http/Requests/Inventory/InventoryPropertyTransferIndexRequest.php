<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class InventoryPropertyTransferIndexRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['submitted', 'verified', 'approved', 'completed', 'rejected', 'cancelled'])], 'asset' => ['nullable', 'integer']];
    }
}
