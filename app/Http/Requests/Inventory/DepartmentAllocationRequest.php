<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class DepartmentAllocationRequest extends ValidatedFormRequest
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
            'issuance_slip_id' => ['required', 'integer', 'exists:issuance_slips,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
        ];
    }
}
