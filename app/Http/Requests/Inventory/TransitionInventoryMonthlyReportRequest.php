<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Models\InventoryMonthlyReport;
use Illuminate\Validation\Rule;

class TransitionInventoryMonthlyReportRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $report = $this->route('report');

        return $user !== null && $user->is_active && $report instanceof InventoryMonthlyReport
            && ($user->can('manage-inventory') || in_array($user->id, [$report->checked_by_user_id, $report->noted_by_user_id, $report->approved_by_user_id], true));
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['check', 'note', 'approve', 'refresh', 'cancel', 'transmit'])], 'snapshot_id' => ['required', 'integer'], 'remarks' => ['required', 'string', 'max:3000'], 'attested' => ['accepted'],
            'signature_reference' => ['required_if:action,check,note,approve', 'nullable', 'string', 'max:255'],
            'transmittal_reference' => ['required_if:action,transmit', 'nullable', 'string', 'max:255'], 'transmittal_recipient' => ['required_if:action,transmit', 'nullable', 'string', 'max:255'], 'jev_reference' => ['nullable', 'string', 'max:120']];
    }
}
