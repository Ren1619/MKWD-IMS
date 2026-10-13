<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\InventoryAssetEventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSemiExpendableEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in([
                InventoryAssetEventType::Transfer->value,
                InventoryAssetEventType::Maintenance->value,
                InventoryAssetEventType::PhysicalCount->value,
                InventoryAssetEventType::Loss->value,
                InventoryAssetEventType::ReliefRequested->value,
                InventoryAssetEventType::ReliefApproved->value,
                InventoryAssetEventType::ReliefDenied->value,
                InventoryAssetEventType::Unserviceable->value,
                InventoryAssetEventType::Disposal->value,
            ])],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_no' => ['required', 'string', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'quantity' => [Rule::requiredIf($this->input('event_type') === 'physical_count'), 'nullable', 'integer', 'min:0'],
            'to_location' => [Rule::requiredIf($this->input('event_type') === 'transfer'), 'nullable', 'string', 'max:255'],
            'condition_status' => [Rule::requiredIf($this->input('event_type') === 'physical_count'), 'nullable', Rule::enum(AssetConditionStatus::class)],
            'counted_by' => [Rule::requiredIf($this->input('event_type') === 'physical_count'), 'nullable', 'string', 'max:255'],
            'count_location' => [Rule::requiredIf($this->input('event_type') === 'physical_count'), 'nullable', 'string', 'max:255'],
            'loss_type' => [Rule::requiredIf($this->input('event_type') === 'loss'), 'nullable', Rule::in(['lost', 'stolen', 'damaged', 'destroyed'])],
            'incident_date' => [Rule::requiredIf($this->input('event_type') === 'loss'), 'nullable', 'date', 'before_or_equal:event_date'],
            'date_discovered' => [Rule::requiredIf($this->input('event_type') === 'loss'), 'nullable', 'date', 'after_or_equal:incident_date', 'before_or_equal:event_date'],
            'security_reference' => ['nullable', 'string', 'max:120'],
            'circumstances' => [Rule::requiredIf($this->input('event_type') === 'loss'), 'nullable', 'string', 'max:3000'],
            'recovery_status' => ['nullable', 'string', 'max:255'],
            'relief_status' => ['nullable', 'string', 'max:255'],
            'liability_status' => ['nullable', 'string', 'max:255'],
            'disposal_method' => [Rule::requiredIf($this->input('event_type') === 'disposal'), 'nullable', 'string', 'max:255'],
            'approval_no' => [Rule::requiredIf(in_array($this->input('event_type'), ['relief_approved', 'relief_denied', 'disposal'], true)), 'nullable', 'string', 'max:120'],
            'approval_date' => [Rule::requiredIf(in_array($this->input('event_type'), ['relief_approved', 'relief_denied', 'disposal'], true)), 'nullable', 'date', 'before_or_equal:today'],
            'proceeds' => [Rule::requiredIf($this->input('event_type') === 'disposal'), 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'appraised_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'notes' => [Rule::requiredIf(in_array($this->input('event_type'), ['maintenance', 'relief_requested', 'relief_approved', 'relief_denied'], true)), 'nullable', 'string', 'max:3000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
