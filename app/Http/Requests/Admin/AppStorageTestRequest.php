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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $drivers = array_keys(config('storage.drivers', []));

        return [
            'driver' => ['required', 'string', Rule::in($drivers)],
            'config' => ['nullable', 'array'],
            'config_id' => ['nullable', 'integer', 'exists:app_storage_configs,id'],
            'cdn_url' => ['nullable', 'string', 'max:500'],
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
        ];
    }
}
