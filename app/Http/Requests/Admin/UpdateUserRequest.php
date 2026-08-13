<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hris_reference_id' => [
                'nullable',
                'integer',
                Rule::exists('hris_references', 'id')
                    ->where('type', 'employee')
                    ->where('is_active', true),
                Rule::unique('users', 'hris_reference_id')->ignore($this->route('user')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['required', new Enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $managedUser = $this->route('user');

                if (
                    $managedUser instanceof User
                    && $this->user()?->is($managedUser)
                    && ($this->string('role')->toString() !== UserRole::SuperAdmin->value || ! $this->boolean('is_active'))
                ) {
                    $validator->errors()->add('role', 'You cannot deactivate or remove your own super admin access.');
                }
            },
        ];
    }
}
