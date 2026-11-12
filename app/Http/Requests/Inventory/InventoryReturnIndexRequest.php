<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class InventoryReturnIndexRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['submitted', 'noted', 'received', 'verified', 'approved', 'rejected', 'cancelled'])],
            'supply_request_id' => ['nullable', 'integer', 'exists:supply_requests,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
