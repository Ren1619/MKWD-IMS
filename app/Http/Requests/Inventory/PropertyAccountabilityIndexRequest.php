<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PropertyAccountabilityIndexRequest extends ValidatedFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending_recipient', 'pending_note', 'active', 'superseded', 'returned', 'cancelled', 'expired', 'relieved', 'transferred_out'])],
            'document_type' => ['nullable', Rule::in(['ARE', 'PAR', 'ICS'])],
            'view' => ['nullable', Rule::in(['all', 'noted'])],
            'queue' => ['nullable', Rule::in(['needs_action', 'completed'])],
        ];
    }
}
