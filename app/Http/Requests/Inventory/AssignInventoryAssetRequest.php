<?php

namespace App\Http\Requests\Inventory;

use App\AssetAccountingClassification;
use App\Http\Requests\ValidatedFormRequest;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AssignInventoryAssetRequest extends ValidatedFormRequest
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
        $asset = $this->route('asset');

        return [
            'attested' => [
                Rule::requiredIf($asset instanceof InventoryAsset && $asset->accounting_classification === AssetAccountingClassification::Ppe),
                'nullable',
                'accepted',
            ],
            'hris_reference_id' => [
                'required',
                'integer',
                Rule::exists('hris_references', 'id')->where(fn ($query) => $query
                    ->where('type', HrisReference::TYPE_EMPLOYEE)
                    ->where('is_active', true)),
            ],
            'replacement_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
