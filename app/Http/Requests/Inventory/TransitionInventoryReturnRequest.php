<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryReturnRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['note', 'receive', 'verify', 'approve', 'reject', 'cancel', 'endorse_accounting'])],
            'attested' => ['accepted'],
            'remarks' => ['required', 'string', 'max:3000'],
            'manager_reference_id' => ['required_if:action,note', 'nullable', 'integer', Rule::exists('hris_references', 'id')->where('type', 'employee')->where('is_active', true)],
            'received_date' => ['required_if:action,receive', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'inspection_reference' => ['nullable', 'string', 'max:120'],
            'accounting_reference' => ['required_if:action,endorse_accounting', 'nullable', 'string', 'max:120'],
            'accounting_recipient' => ['required_if:action,endorse_accounting', 'nullable', 'string', 'max:255'],
            'lines' => ['required_if:action,verify', 'nullable', 'array', 'max:25'],
            'lines.*' => ['required', 'array:line_id,quantity_accepted,condition,warehouse_id,storage_location,inspection_remarks'],
            'lines.*.line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity_accepted' => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.condition' => ['required', Rule::in(['good', 'defective', 'discrepant'])],
            'lines.*.warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'lines.*.storage_location' => ['nullable', 'string', 'max:150'],
            'lines.*.inspection_remarks' => ['required', 'string', 'max:3000'],
        ];
    }
}
