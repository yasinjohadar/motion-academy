<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => 'الاسم بالعربي',
            'name_en' => 'الاسم بالإنكليزي',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'password' => 'كلمة المرور',
            'roles' => 'الأدوار',
        ];
    }
}
