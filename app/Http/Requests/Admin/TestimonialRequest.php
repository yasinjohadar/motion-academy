<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TestimonialRequest extends FormRequest
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
            'role' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'min:10', 'max:1000'],
            'rating' => ['required', 'numeric', 'between:1,5', 'multiple_of:0.5'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'avatar_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'icon' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\- ]+$/i'],
            'color' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'role' => 'المسمى الوظيفي',
            'company' => 'الجهة',
            'content' => 'نص الرأي',
            'rating' => 'التقييم',
            'avatar' => 'الصورة',
            'avatar_url' => 'رابط الصورة',
            'icon' => 'الأيقونة',
            'color' => 'اللون',
            'course_id' => 'الدورة',
            'sort_order' => 'الترتيب',
        ];
    }
}
