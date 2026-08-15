<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmtpSettingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الإعداد',
            'host' => 'المضيف',
            'port' => 'المنفذ',
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
            'encryption' => 'التشفير',
            'from_address' => 'بريد المرسل',
            'from_name' => 'اسم المرسل',
            'is_default' => 'افتراضي',
            'is_active' => 'نشط',
        ];
    }
}
