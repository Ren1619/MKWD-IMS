<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;

class StoreInventoryCountRequest extends ValidatedFormRequest
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter a count title.',
            'scheduled_date.required' => 'Select a scheduled date.',
            'scheduled_date.date_format' => 'Select a valid scheduled date.',
            'memorandum_reference.required' => 'Enter the approved memorandum reference.',
            'team_details.required' => 'Enter the team and counting arrangements.',
            'attested.accepted' => 'Confirm that this plan follows the approved inventory memorandum.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'count title',
            'scheduled_date' => 'scheduled date',
            'memorandum_reference' => 'approved memorandum reference',
            'team_details' => 'team and counting arrangements',
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
