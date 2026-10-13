<?php

namespace App\Http\Requests\Inventory;

use App\Models\HrisReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $employee = fn () => Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true);

        return [
            'action' => ['required', Rule::in(['approve', 'complete', 'reject', 'cancel'])],
            'attestation' => ['required', 'string', 'max:2000'],
            'chair_reference_id' => ['required_if:action,approve', 'nullable', 'integer', $employee()],
            'approval_no' => ['required_if:action,approve', 'nullable', 'string', 'max:120'],
            'approval_date' => ['required_if:action,approve', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'disposition_date' => ['required_if:action,complete', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'disposition_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'inspector_reference_id' => ['required_if:action,complete', 'nullable', 'integer', $employee()],
            'internal_control_reference_id' => ['required_if:action,complete', 'nullable', 'integer', 'different:inspector_reference_id', $employee()],
            'accounting_witness_reference_id' => ['required_if:action,complete', 'nullable', 'integer', 'different:inspector_reference_id,internal_control_reference_id', $employee()],
            'inspection_certificate_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'internal_control_signature_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'accounting_signature_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'accounting_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'lines' => ['required_if:action,complete', 'nullable', 'array', 'min:1', 'max:50'],
            'lines.*' => ['array:id,proceeds,or_number'],
            'lines.*.id' => ['required', 'integer', 'distinct'],
            'lines.*.proceeds' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'lines.*.or_number' => ['nullable', 'string', 'max:120'],
        ];
    }
}
