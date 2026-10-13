<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->boolean('issuance_form')) {
            return [
                'issuance_form' => ['required', 'accepted'],
                'purpose' => ['required', 'string', 'max:2000'],
                'responsibility_center_code' => ['nullable', 'string', Rule::in([])],
                'lines' => ['required', 'array', 'min:1', 'max:25'],
                'lines.*' => ['required', 'array:inventory_item_id,item_name,unit_of_measure,quantity'],
                'lines.*.inventory_item_id' => ['nullable', Rule::exists('inventory_items', 'inventory_item_id')->whereNull('deleted_at')->where('status', 'active')],
                'lines.*.item_name' => ['required', 'string', 'max:255'],
                'lines.*.unit_of_measure' => ['required', 'string', 'max:50'],
                'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            ];
        }

        return [
            'requester_reference_id' => [
                'nullable',
                Rule::prohibitedIf(! $this->user()?->canManageInventory()),
                Rule::exists('hris_references', 'id')
                    ->where('type', 'employee')
                    ->where('is_active', true),
            ],
            'office_name' => ['nullable', 'string', 'max:150'], 'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'purpose' => ['required', 'string', 'max:2000'], 'date_needed' => ['nullable', 'date', 'after_or_equal:today'],
            'lines' => ['required', 'array', 'min:1', 'max:25'], 'lines.*.inventory_item_id' => ['nullable', Rule::exists('inventory_items', 'inventory_item_id')->whereNull('deleted_at')],
            'lines.*.is_new_item' => ['required', 'boolean'], 'lines.*.item_name' => ['required_if:lines.*.is_new_item,true', 'nullable', 'string', 'max:255'],
            'lines.*.specifications' => ['nullable', 'string', 'max:2000'], 'lines.*.unit_of_measure' => ['required_if:lines.*.is_new_item,true', 'nullable', 'string', 'max:50'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lines.*.justification' => ['required_if:lines.*.is_new_item,true', 'nullable', 'string', 'max:2000'],
        ];
    }
}
