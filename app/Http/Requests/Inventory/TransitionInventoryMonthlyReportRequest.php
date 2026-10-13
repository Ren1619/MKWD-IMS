<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['check', 'note', 'approve', 'refresh', 'cancel', 'transmit'])], 'snapshot_id' => ['required', 'integer'], 'remarks' => ['required', 'string', 'max:3000'], 'attested' => ['accepted'],
            'signature_reference' => ['required_if:action,check,note,approve', 'nullable', 'string', 'max:255'],
            'transmittal_reference' => ['required_if:action,transmit', 'nullable', 'string', 'max:255'], 'transmittal_recipient' => ['required_if:action,transmit', 'nullable', 'string', 'max:255'], 'jev_reference' => ['nullable', 'string', 'max:120']];
    }
}
