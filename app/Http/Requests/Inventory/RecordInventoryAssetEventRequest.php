<?php

namespace App\Http\Requests\Inventory;

use App\AssetConditionStatus;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordInventoryAssetEventRequest extends FormRequest
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
        $type = $this->string('event_type')->toString();

        return [
            'event_type' => [
                'required',
                Rule::in(array_map(
                    fn (InventoryAssetEventType $eventType): string => $eventType->value,
                    InventoryAssetEventType::userRecordable(),
                )),
            ],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_no' => ['required', 'string', 'max:120'],
            'amount' => [
                Rule::requiredIf(in_array($type, ['capital_addition', 'maintenance', 'impairment', 'impairment_reversal'], true)),
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999999999.99',
            ],
            'remaining_useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'to_location' => [Rule::requiredIf($type === 'transfer'), 'nullable', 'string', 'max:255'],
            'quantity' => [Rule::requiredIf($type === 'physical_count'), 'nullable', 'integer', 'min:0', 'max:1000000'],
            'condition_status' => [
                Rule::requiredIf(in_array($type, ['physical_count', 'unserviceable'], true)),
                'nullable',
                Rule::enum(AssetConditionStatus::class),
            ],
            'vendor' => ['nullable', 'string', 'max:255'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'parts_used' => ['nullable', 'string', 'max:3000'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'counted_by' => [Rule::requiredIf($type === 'physical_count'), 'nullable', 'string', 'max:255'],
            'count_location' => [Rule::requiredIf($type === 'physical_count'), 'nullable', 'string', 'max:255'],
            'impairment_trigger' => [Rule::requiredIf(in_array($type, ['impairment', 'impairment_reversal'], true)), 'nullable', 'string', 'max:1000'],
            'assessment_basis' => [Rule::requiredIf(in_array($type, ['impairment', 'impairment_reversal'], true)), 'nullable', 'string', 'max:3000'],
            'loss_type' => [Rule::requiredIf($type === 'loss'), 'nullable', 'string', 'max:100'],
            'circumstances' => [Rule::requiredIf($type === 'loss'), 'nullable', 'string', 'max:3000'],
            'recovery_status' => ['nullable', 'string', 'max:255'],
            'relief_status' => ['nullable', 'string', 'max:255'],
            'liability_status' => ['nullable', 'string', 'max:255'],
            'disposal_method' => [Rule::requiredIf($type === 'disposal'), 'nullable', 'string', 'max:255'],
            'approval_no' => [Rule::requiredIf($type === 'disposal'), 'nullable', 'string', 'max:120'],
            'approval_date' => [Rule::requiredIf($type === 'disposal'), 'nullable', 'date', 'before_or_equal:today'],
            'proceeds' => [Rule::requiredIf($type === 'disposal'), 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'appraised_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'notes' => [Rule::requiredIf($type === 'maintenance'), 'nullable', 'string', 'max:3000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $asset = $this->route('asset');

            if (! $asset instanceof InventoryAsset || ! $this->date('event_date')) {
                return;
            }

            if ($this->date('event_date')->lt($asset->acquisition_date)) {
                $validator->errors()->add(
                    'event_date',
                    'The event date cannot be earlier than the acquisition date.',
                );
            }

            if (
                $this->string('event_type')->toString() === 'disposal'
                && $this->date('approval_date')?->gt($this->date('event_date'))
            ) {
                $validator->errors()->add(
                    'approval_date',
                    'The disposal approval date cannot be later than the disposal date.',
                );
            }
        }];
    }
}
