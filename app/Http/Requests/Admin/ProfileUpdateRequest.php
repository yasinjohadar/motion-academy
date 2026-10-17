<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Optional here so older forms keep working; normalised and checked for
            // uniqueness in ProfileController::update via PhoneInput.
            'country_code' => ['nullable', 'string', 'regex:/^\+?\d{1,4}$/'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
