<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الدور',
            'permissions' => 'الصلاحيات',
        ];
    }
}
