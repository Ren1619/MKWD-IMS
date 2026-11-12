<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Models\HrisReference;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PrintIssuanceSlipRequest extends ValidatedFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true
            && $this->route('slip')?->supplyRequest?->status === 'released';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = ['nullable', 'integer', Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)];

        return ['prepared_by' => $employee, 'checked_by' => $employee, 'approved_by' => $employee];
    }
}
