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
        $driver = (string) $this->input('driver');
        $url = ['nullable', 'string', 'max:500', 'url:http,https'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in(array_keys(config('storage.drivers', [])))],
            'config' => ['required', 'array'],
            'config.access_key_id' => [$this->secretRule('access_key_id'), 'string', 'max:255'],
            'config.secret_access_key' => [$this->secretRule('secret_access_key'), 'string', 'max:255'],
            'config.region' => [Rule::requiredIf(in_array($driver, ['s3', 'idrive_e2'], true)), 'nullable', 'string', 'max:100'],
            'config.bucket' => ['required', 'string', 'max:255'],
            'config.endpoint' => [Rule::requiredIf($driver === 'idrive_e2'), ...$url],
            'config.url' => $url,
            // R2 files are only publicly reachable through the bucket's public domain.
            'config.public_url' => [Rule::requiredIf($driver === 'cloudflare_r2'), ...$url],
            'config.account_id' => [Rule::requiredIf($driver === 'cloudflare_r2'), 'nullable', 'string', 'max:255'],
            'config.use_path_style' => ['nullable', 'boolean'],
            'config.public_acl' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cdn_url' => $url,
        ];
    }

    /**
     * Keys are required when creating; on update a blank secret keeps the stored one.
     */
    protected function secretRule(string $key): string
    {
        return 'required';
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم الحساب',
            'driver' => 'نوع التخزين',
            'config.access_key_id' => 'مفتاح الوصول',
            'config.secret_access_key' => 'المفتاح السري',
            'config.region' => 'المنطقة',
            'config.bucket' => 'اسم الحاوية',
            'config.endpoint' => 'نقطة النهاية',
            'config.url' => 'الرابط العام',
            'config.public_url' => 'الرابط العام للحاوية',
            'config.account_id' => 'معرّف الحساب',
            'cdn_url' => 'رابط CDN',
        ];
    }
}
