<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppStorageTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every address the test will call must be a real web URL — the test makes
     * outbound requests with the account's credentials.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $url = ['nullable', 'string', 'max:500', 'url:http,https'];

        return [
            'driver' => ['required', 'string', Rule::in(array_keys(config('storage.drivers', [])))],
            'config' => ['nullable', 'array'],
            'config.endpoint' => $url,
            'config.url' => $url,
            'config.public_url' => $url,
            'config_id' => ['nullable', 'integer', 'exists:app_storage_configs,id'],
            'cdn_url' => $url,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'driver' => 'نوع التخزين',
            'config' => 'الإعدادات',
            'config.endpoint' => 'نقطة النهاية',
            'config.public_url' => 'الرابط العام',
            'cdn_url' => 'رابط CDN',
        ];
    }
}
