<?php

namespace App\Http\Requests\Inventory;

use App\Models\HrisReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'place_of_storage' => ['required', 'string', 'max:255'],
            'method' => ['required', Rule::in(['destroyed', 'private_sale', 'public_auction'])],
            'reason' => ['required', 'string', 'max:2000'],
            'certifier_reference_id' => ['required', 'integer', Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true)],
            'certification_reference' => ['required', 'string', 'max:120'],
            'certification_attestation' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*' => ['array:inventory_asset_id,quantity,unit,description,source_reference'],
            'lines.*.inventory_asset_id' => ['nullable', 'integer', Rule::exists('inventory_assets', 'inventory_asset_id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit' => ['required', 'string', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:2000'],
            'lines.*.source_reference' => ['required', 'string', 'max:120'],
        ];
    }
}
