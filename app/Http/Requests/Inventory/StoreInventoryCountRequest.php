<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date_format:Y-m-d'],
            'memorandum_reference' => ['required', 'string', 'max:120'],
            'team_details' => ['required', 'string', 'max:3000'],
            'attested' => ['accepted'],
        ];
    }

    /** @return array{title: string, scheduled_date: string, memorandum_reference: string, team_details: string} */
    public function countPlan(): array
    {
        return [
            'title' => $this->validated('title'),
            'scheduled_date' => $this->validated('scheduled_date'),
            'memorandum_reference' => $this->validated('memorandum_reference'),
            'team_details' => $this->validated('team_details'),
        ];
    }
}
