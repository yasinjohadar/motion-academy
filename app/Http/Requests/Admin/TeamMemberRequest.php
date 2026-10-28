<?php

namespace App\Http\Requests\Admin;

use App\Support\SocialPlatforms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeamMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\- ]+$/i'],
            'color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'rating' => ['nullable', 'numeric', 'between:1,5', 'multiple_of:0.1'],
            'profile_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/).+/i'],
            'social_links' => ['nullable', 'array', 'max:8'],
            'social_links.*.platform' => ['required', Rule::in(SocialPlatforms::keys())],
            'social_links.*.url' => ['required', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * A social link must be a web URL, except email which takes an address.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => SocialPlatforms::validateUrls($validator, $this->input('social_links', []), 'social_links'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'title' => 'المسمى الوظيفي',
            'bio' => 'النبذة',
            'avatar' => 'الصورة',
            'avatar_url' => 'رابط الصورة',
            'icon' => 'الأيقونة',
            'color' => 'اللون',
            'rating' => 'التقييم',
            'profile_url' => 'رابط الملف الشخصي',
            'social_links' => 'روابط التواصل',
            'social_links.*.platform' => 'المنصة',
            'social_links.*.url' => 'الرابط',
            'sort_order' => 'الترتيب',
        ];
    }
}
