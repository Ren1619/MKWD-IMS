<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryBatchPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [
            'latest_placement_id' => ['present', 'nullable', 'integer', 'min:1'],
            'warehouse' => ['required', 'string', 'max:150'], 'area' => ['required', 'string', 'max:150'],
            'rack' => ['nullable', 'string', 'max:100'], 'bin' => ['nullable', 'string', 'max:100'],
            'observed_quantity' => ['required', 'integer', 'min:1'],
            'source_evidence_reference' => ['required', 'string', 'max:255'],
            'remarks' => ['required', 'string', 'max:3000'], 'attested' => ['required', 'accepted'],
        ];
    }
}
