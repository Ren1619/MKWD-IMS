<?php

namespace App\Http\Requests\Inventory;

use App\Models\HrisReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryPropertyTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $external = in_array($this->input('transfer_type'), ['agency_transfer', 'donation'], true);

        return [
            'inventory_asset_id' => ['required', 'integer', Rule::exists('inventory_assets', 'inventory_asset_id')->whereNull('deleted_at')],
            'recipient_reference_id' => [Rule::prohibitedIf($external), Rule::requiredIf(! $external), 'nullable', 'integer', Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)],
            'transfer_type' => ['required', Rule::in(['reassignment', 'resignation', 'relocation', 'other', 'agency_transfer', 'donation'])],
            'agency_name' => [Rule::requiredIf($external), Rule::prohibitedIf(! $external), 'nullable', 'string', 'max:255'],
            'agency_officer_name' => [Rule::requiredIf($external), Rule::prohibitedIf(! $external), 'nullable', 'string', 'max:255'],
            'agency_officer_position' => [Rule::requiredIf($external), Rule::prohibitedIf(! $external), 'nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
            'destination_location' => ['required', 'string', 'max:255'],
            'desired_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'replacement_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
