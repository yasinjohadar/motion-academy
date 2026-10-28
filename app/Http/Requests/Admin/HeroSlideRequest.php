<?php

namespace App\Http\Requests\Admin;

use App\Enums\HeroSlideVisual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeroSlideRequest extends FormRequest
{
    /** A link is a full URL, a site path ("/courses") or an in-page anchor ("#about"). */
    private const LINK_PATTERN = '/^(https?:\/\/|\/|#).+/i';

    /** Font Awesome class list, e.g. "fas fa-play". */
    private const ICON_PATTERN = '/^[a-z0-9\- ]+$/i';

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
            'badge_text' => ['nullable', 'string', 'max:120'],
            'badge_icon' => ['nullable', 'string', 'max:80', 'regex:'.self::ICON_PATTERN],
            'title' => ['required', 'string', 'max:200'],
            'title_highlight' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],

            'primary_button_label' => ['nullable', 'required_with:primary_button_url', 'string', 'max:80'],
            'primary_button_url' => ['nullable', 'required_with:primary_button_label', 'string', 'max:500', 'regex:'.self::LINK_PATTERN],
            'primary_button_icon' => ['nullable', 'string', 'max:80', 'regex:'.self::ICON_PATTERN],
            'secondary_button_label' => ['nullable', 'required_with:secondary_button_url', 'string', 'max:80'],
            'secondary_button_url' => ['nullable', 'required_with:secondary_button_label', 'string', 'max:500', 'regex:'.self::LINK_PATTERN],
            'secondary_button_icon' => ['nullable', 'string', 'max:80', 'regex:'.self::ICON_PATTERN],

            'bullet_label' => ['nullable', 'string', 'max:60'],
            'visual' => ['required', Rule::enum(HeroSlideVisual::class)],
            'image' => ['nullable', 'image', 'max:4096'],
            'image_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'remove_image' => ['sometimes', 'boolean'],
            'image_alt' => ['nullable', 'string', 'max:200'],
            'show_stats' => ['nullable', 'boolean'],

            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'badge_text' => 'نص الشارة',
            'badge_icon' => 'أيقونة الشارة',
            'title' => 'العنوان',
            'title_highlight' => 'الجزء المميز من العنوان',
            'description' => 'الوصف',
            'primary_button_label' => 'نص الزر الرئيسي',
            'primary_button_url' => 'رابط الزر الرئيسي',
            'primary_button_icon' => 'أيقونة الزر الرئيسي',
            'secondary_button_label' => 'نص الزر الثانوي',
            'secondary_button_url' => 'رابط الزر الثانوي',
            'secondary_button_icon' => 'أيقونة الزر الثانوي',
            'bullet_label' => 'عنوان النقطة',
            'visual' => 'العنصر المرئي',
            'image' => 'الصورة',
            'image_url' => 'رابط الصورة',
            'image_alt' => 'النص البديل للصورة',
            'sort_order' => 'الترتيب',
            'starts_at' => 'تاريخ البدء',
            'ends_at' => 'تاريخ الانتهاء',
        ];
    }
}
