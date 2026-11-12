<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use App\Models\HrisReference;
use App\Models\InventoryAsset;
use Illuminate\Validation\Rule;

class PrintInventoryAssetLapsingScheduleRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');
        $user = $this->user();

        return $asset instanceof InventoryAsset && $user !== null
            && ($user->canViewAllPropertyAssets()
                || ($user->hris_reference_id !== null && $asset->current_custodian_reference_id === $user->hris_reference_id));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:1900', 'max:9999'],
            'prepared_by' => ['required', 'integer', Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)],
            'certified_by' => ['required', 'integer', 'different:prepared_by', Rule::exists('hris_references', 'id')->where('type', HrisReference::TYPE_EMPLOYEE)],
        ];
    }
}
