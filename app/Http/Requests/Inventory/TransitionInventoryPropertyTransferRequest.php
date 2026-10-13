<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\Models\InventoryPropertyTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInventoryPropertyTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $transfer = $this->route('transfer');
        $external = $transfer instanceof InventoryPropertyTransfer && $transfer->isExternal();

        return [
            'action' => ['required', Rule::in(['verify', 'approve', 'complete', 'reject', 'cancel'])],
            'attestation' => ['required', 'string', 'max:2000'],
            'inspection_reference' => ['required_if:action,verify,complete', 'nullable', 'string', 'max:120'],
            'condition_status' => ['required_if:action,complete', 'nullable', Rule::enum(AssetConditionStatus::class)],
            'handover_date' => ['required_if:action,complete', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'release_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'receipt_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'authority_reference' => [Rule::requiredIf($external && $this->input('action') === 'approve'), 'nullable', 'string', 'max:120'],
            'accounting_clearance_reference' => [Rule::requiredIf($external && $this->input('action') === 'approve'), 'nullable', 'string', 'max:120'],
            'accounting_reference' => [Rule::requiredIf($external && $this->input('action') === 'complete'), 'nullable', 'string', 'max:120'],
            'ownership_transfer_confirmed' => [Rule::when($external && $this->input('action') === 'complete', ['required', 'accepted']), 'nullable'],
        ];
    }
}
