<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Models\SupplyRequest;
use App\Services\TemporaryHeadApprovalAccess;
use Illuminate\Validation\Rule;

class TransitionSupplyRequestRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $record = $this->route('supplyRequest');

        return $user !== null && (($this->input('action') !== 'approve' && $user->can('manage-inventory'))
            || ($record instanceof SupplyRequest && in_array($this->input('action'), ['approve', 'reject'], true)
                && app(TemporaryHeadApprovalAccess::class)->canApprove($user, $record)));
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'review', 'release', 'reject', 'cancel'])], 'attested' => ['accepted'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'procurement_mode' => ['nullable', 'string', 'max:100'],
        ];
    }
}
