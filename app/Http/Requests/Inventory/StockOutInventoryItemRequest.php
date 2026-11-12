<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class StockOutInventoryItemRequest extends ValidatedFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'recipient_reference_id' => ['nullable', 'integer', 'exists:hris_references,id', 'required_without:recipient_name'],
            'recipient_name' => ['nullable', 'string', 'max:255', 'required_without:recipient_reference_id'],
            'ris_no' => ['nullable', 'string', 'max:100'],
            'responsibility_center_code' => ['nullable', 'string', 'max:100'],
            'stocked_out_at' => ['nullable', 'date', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
