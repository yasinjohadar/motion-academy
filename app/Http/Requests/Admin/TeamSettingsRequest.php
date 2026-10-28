<?php

namespace App\Http\Requests\Admin;

use App\Services\Frontend\TeamSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamSettingsRequest extends FormRequest
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
            'order' => ['required', Rule::in(array_keys(TeamSettings::ORDERS))],
            'layout' => ['required', Rule::in(array_keys(TeamSettings::LAYOUTS))],
            'columns' => ['required', 'integer', Rule::in(TeamSettings::COLUMNS)],
            'show_rating' => ['required', 'boolean'],
            'show_social' => ['required', 'boolean'],
            'autoplay' => ['required', 'boolean'],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:60'],
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|#).+/i'],
        ];
    }

    /**
     * Empty text fields are stored as empty strings, so clearing one hides it
     * (an empty button hides the call to action) rather than restoring the default.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            ...$this->validated(),
            'eyebrow' => (string) $this->validated('eyebrow'),
            'subtitle' => (string) $this->validated('subtitle'),
            'cta_label' => (string) $this->validated('cta_label'),
            'cta_url' => (string) $this->validated('cta_url'),
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
            'limit' => 'عدد الأعضاء في الرئيسية',
            'order' => 'ترتيب العرض',
            'layout' => 'طريقة العرض',
            'columns' => 'عدد الأعمدة',
            'cta_label' => 'نص الزر',
            'cta_url' => 'رابط الزر',
        ];
    }
}
