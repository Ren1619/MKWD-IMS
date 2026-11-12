<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\ValidatedFormRequest;
use Illuminate\Validation\Rule;

class TransitionPropertyAccountabilityRequest extends ValidatedFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                Rule::in(['acknowledge', 'note', 'witnessed_acknowledge', 'renew', 'return', 'cancel']),
            ],
            'attested' => ['accepted'],
            'remarks' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $this->string('action')->toString(),
                    ['witnessed_acknowledge', 'renew', 'return', 'cancel'],
                    true,
                )),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
