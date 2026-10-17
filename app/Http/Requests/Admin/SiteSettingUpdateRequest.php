<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:150'],
            'site_name_en' => ['nullable', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_whatsapp' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'logo_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'favicon_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'seo_og_image' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'twitter_handle' => ['nullable', 'string', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],
            'google_site_verification' => ['nullable', 'string', 'max:500'],
            'bing_site_verification' => ['nullable', 'string', 'max:500'],
            'allow_indexing' => ['nullable', 'boolean'],
            'social_links' => ['nullable', 'array'],
            'social_links.*.id' => ['nullable', 'integer'],
            'social_links.*.label' => ['required_with:social_links', 'string', 'max:100'],
            'social_links.*.url' => ['required_with:social_links', 'url', 'max:500'],
            'social_links.*.icon' => ['required_with:social_links', 'string', 'max:80'],
            'social_links.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'social_links.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'site_name' => 'اسم الموقع',
            'logo_url' => 'رابط اللوغو',
            'favicon_url' => 'رابط الأيقونة',
            'primary_whatsapp' => 'رقم الواتساب',
            'seo_title' => 'عنوان الصفحة الرئيسية',
            'seo_description' => 'الوصف الافتراضي',
            'seo_keywords' => 'الكلمات المفتاحية',
            'seo_og_image' => 'صورة المشاركة الافتراضية',
            'twitter_handle' => 'حساب X (تويتر)',
            'google_site_verification' => 'رمز تحقق Google',
            'bing_site_verification' => 'رمز تحقق Bing',
            'social_links.*.label' => 'اسم الرابط',
            'social_links.*.url' => 'رابط السوشيال',
            'social_links.*.icon' => 'أيقونة السوشيال',
        ];
    }

    public function messages(): array
    {
        return [
            'logo_url.regex' => 'صيغة رابط اللوغو غير صحيحة.',
            'favicon_url.regex' => 'صيغة رابط الأيقونة غير صحيحة.',
            'seo_og_image.regex' => 'صيغة رابط الصورة غير صحيحة.',
            'twitter_handle.regex' => 'اكتب اسم الحساب فقط، مثل @MotionAcademy.',
        ];
    }
}
