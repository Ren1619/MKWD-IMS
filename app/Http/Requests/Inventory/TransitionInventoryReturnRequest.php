<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['verify', 'approve', 'reject', 'cancel', 'endorse_accounting'])],
            'attested' => ['accepted'],
            'remarks' => ['required', 'string', 'max:3000'],
            'inspection_reference' => ['required_if:action,verify', 'nullable', 'string', 'max:120'],
            'accounting_reference' => ['required_if:action,endorse_accounting', 'nullable', 'string', 'max:120'],
            'accounting_recipient' => ['required_if:action,endorse_accounting', 'nullable', 'string', 'max:255'],
            'lines' => ['required_if:action,verify', 'nullable', 'array', 'max:25'],
            'lines.*' => ['required', 'array:line_id,quantity_accepted,condition,storage_location,inspection_remarks'],
            'lines.*.line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_accepted' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.condition' => ['required', Rule::in(['good', 'defective', 'discrepant'])],
            'lines.*.storage_location' => ['nullable', 'string', 'max:255'],
            'lines.*.inspection_remarks' => ['required', 'string', 'max:3000'],
        ];
    }
}
