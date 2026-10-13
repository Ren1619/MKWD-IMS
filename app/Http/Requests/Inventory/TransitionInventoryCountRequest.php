<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['start', 'submit', 'verify', 'approve', 'return_for_recount', 'cancel', 'emergency_issue', 'segregated_receipt'])],
            'idempotency_key' => ['required_if:action,emergency_issue,segregated_receipt', 'nullable', 'uuid'],
            'attested' => ['accepted'],
            'remarks' => ['required_if:action,cancel,return_for_recount,approve,emergency_issue,segregated_receipt', 'nullable', 'string', 'max:3000'],
            'line_id' => ['required_if:action,emergency_issue,segregated_receipt', 'nullable', 'integer'],
            'quantity' => ['required_if:action,emergency_issue,segregated_receipt', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'reference_no' => ['required_if:action,emergency_issue,segregated_receipt', 'nullable', 'string', 'max:100'],
            'approval_reference' => ['required_if:action,emergency_issue,segregated_receipt', 'nullable', 'string', 'max:120'],
            'recipient_name' => ['required_if:action,emergency_issue', 'nullable', 'string', 'max:255'],
            'timing' => ['required_if:action,emergency_issue', 'nullable', Rule::in(['before_count', 'after_count'])],
            'unit_cost' => ['required_if:action,segregated_receipt', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
