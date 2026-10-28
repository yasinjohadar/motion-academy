<?php

namespace App\Http\Requests\Admin;

use App\Services\Frontend\TestimonialSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestimonialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'limit' => ['required', 'integer', 'min:0', 'max:24'],
            'order' => ['required', Rule::in(array_keys(TestimonialSettings::ORDERS))],
            'featured_only' => ['required', 'boolean'],
            'layout' => ['required', Rule::in(array_keys(TestimonialSettings::LAYOUTS))],
            'columns' => ['required', 'integer', Rule::in(TestimonialSettings::COLUMNS)],
            'show_rating' => ['required', 'boolean'],
            'autoplay' => ['required', 'boolean'],
        ];
    }

    /**
     * Empty text fields are stored as empty strings, so clearing the eyebrow or
     * subtitle hides it rather than restoring the default.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            ...$this->validated(),
            'eyebrow' => (string) $this->validated('eyebrow'),
            'subtitle' => (string) $this->validated('subtitle'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'eyebrow' => 'العنوان الصغير',
            'title' => 'عنوان القسم',
            'subtitle' => 'وصف القسم',
            'limit' => 'عدد الآراء في الرئيسية',
            'order' => 'ترتيب العرض',
            'layout' => 'طريقة العرض',
            'columns' => 'عدد الأعمدة',
        ];
    }
}
