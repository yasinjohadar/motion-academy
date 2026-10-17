<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'account_type' => ['required', Rule::in(array_keys(AccountType::LABELS))],
        ];
    }

    /**
     * Demoting an account to student must never leave the panel without an admin,
     * nor lock the person doing it out of the page they are on.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');
                if (! $user instanceof User || $this->input('account_type') !== AccountType::STUDENT || ! $user->hasRole(AccountType::ADMIN)) {
                    return;
                }

                if ($user->is($this->user())) {
                    $validator->errors()->add('account_type', 'لا يمكنك تحويل حسابك أنت إلى طالب.');
                } elseif (User::role(AccountType::ADMIN)->count() <= 1) {
                    $validator->errors()->add('account_type', 'هذا آخر حساب أدمن — أضف أدمن آخر أولاً.');
                }
            },
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
            'account_type' => 'نوع الحساب',
        ];
    }
}
