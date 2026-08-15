<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppStorageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $drivers = array_keys(config('storage.drivers', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in($drivers)],
            'config' => ['nullable', 'array'],
            'config.path' => ['nullable', 'string', 'max:255'],
            'config.access_key_id' => ['nullable', 'string', 'max:255'],
            'config.secret_access_key' => ['nullable', 'string', 'max:255'],
            'config.region' => ['nullable', 'string', 'max:100'],
            'config.bucket' => ['nullable', 'string', 'max:255'],
            'config.endpoint' => ['nullable', 'string', 'max:500'],
            'config.url' => ['nullable', 'string', 'max:500'],
            'config.public_url' => ['nullable', 'string', 'max:500'],
            'config.account_id' => ['nullable', 'string', 'max:255'],
            'config.use_path_style' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cdn_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم الإعداد',
            'driver' => 'نوع التخزين',
            'config.access_key_id' => 'مفتاح الوصول',
            'config.secret_access_key' => 'المفتاح السري',
            'config.region' => 'المنطقة',
            'config.bucket' => 'اسم الحاوية',
            'config.endpoint' => 'نقطة النهاية',
            'config.account_id' => 'معرّف الحساب',
            'cdn_url' => 'رابط CDN',
        ];
    }
}
