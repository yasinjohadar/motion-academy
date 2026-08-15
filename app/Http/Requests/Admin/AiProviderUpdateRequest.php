<?php

namespace App\Http\Requests\Admin;

use App\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driver = (string) $this->input('driver');
        $needsUrl = in_array($driver, ['ollama', 'openai-compatible'], true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', Rule::in(array_keys(AiProvider::DRIVERS))],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'base_url' => [$needsUrl ? 'required' : 'nullable', 'string', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المزود',
            'driver' => 'نوع المزود',
            'api_key' => 'مفتاح API',
            'base_url' => 'Base URL',
            'default_model' => 'النموذج الافتراضي',
            'is_default' => 'افتراضي',
            'is_active' => 'نشط',
        ];
    }
}
