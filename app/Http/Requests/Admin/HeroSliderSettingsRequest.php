<?php

namespace App\Http\Requests\Admin;

use App\Services\Frontend\HeroSliderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeroSliderSettingsRequest extends FormRequest
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
            'autoplay' => ['required', 'boolean'],
            'autoplay_delay' => ['required', 'integer', 'min:1500', 'max:30000'],
            'speed' => ['required', 'integer', 'min:200', 'max:3000'],
            'effect' => ['required', Rule::in(HeroSliderSettings::EFFECTS)],
            'loop' => ['required', 'boolean'],
            'pause_on_hover' => ['required', 'boolean'],
            'show_navigation' => ['required', 'boolean'],
            'show_pagination' => ['required', 'boolean'],
            'show_progress' => ['required', 'boolean'],
            'max_slides' => ['required', 'integer', 'min:0', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'autoplay_delay' => 'مدة عرض الشريحة',
            'speed' => 'سرعة الانتقال',
            'effect' => 'تأثير الانتقال',
            'max_slides' => 'الحد الأقصى للشرائح',
        ];
    }
}
