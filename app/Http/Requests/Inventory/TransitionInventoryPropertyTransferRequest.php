<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\Http\Requests\ValidatedFormRequest;
use App\Models\InventoryPropertyTransfer;
use App\Services\InventoryPropertyTransferService;
use Illuminate\Validation\Rule;

class TransitionInventoryPropertyTransferRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('transfer');
        if ($this->input('action') === 'acknowledge_receipt') {
            return $transfer instanceof InventoryPropertyTransfer && $this->user() !== null
                && in_array('acknowledge_receipt', app(InventoryPropertyTransferService::class)->allowedActions($transfer, $this->user()), true);
        }

        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $transfer = $this->route('transfer');
        $external = $transfer instanceof InventoryPropertyTransfer && $transfer->isExternal();
        $hasReceipt = $transfer instanceof InventoryPropertyTransfer && $transfer->actions()->where('action', 'acknowledge_receipt')->exists();

        return [
            'action' => ['required', Rule::in(['verify', 'approve', 'complete', 'reject', 'cancel', 'acknowledge_receipt'])],
            'attested' => [Rule::when($this->input('action') === 'acknowledge_receipt', ['required', 'accepted']), 'nullable'],
            'attestation' => ['required', 'string', 'max:2000'],
            'inspection_reference' => ['required_if:action,verify,complete', 'nullable', 'string', 'max:120'],
            'condition_status' => ['required_if:action,complete', 'nullable', Rule::enum(AssetConditionStatus::class)],
            'handover_date' => ['required_if:action,complete,acknowledge_receipt', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'release_reference' => ['required_if:action,complete', 'nullable', 'string', 'max:120'],
            'receipt_reference' => [Rule::requiredIf($this->input('action') === 'complete' && ! $hasReceipt), 'nullable', 'string', 'max:120'],
            'authority_reference' => [Rule::requiredIf($external && $this->input('action') === 'approve'), 'nullable', 'string', 'max:120'],
            'accounting_clearance_reference' => [Rule::requiredIf($external && $this->input('action') === 'approve'), 'nullable', 'string', 'max:120'],
            'accounting_reference' => [Rule::requiredIf($external && $this->input('action') === 'complete'), 'nullable', 'string', 'max:120'],
            'ownership_transfer_confirmed' => [Rule::when($external && $this->input('action') === 'complete', ['required', 'accepted']), 'nullable'],
        ];
    }
}
