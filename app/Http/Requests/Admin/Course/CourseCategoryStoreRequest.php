<?php

namespace App\Http\Requests\Admin\Course;

use App\Models\CourseCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseCategoryStoreRequest extends FormRequest
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
        $categoryId = $this->categoryId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('course_categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:20000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('course_categories', 'id')->whereNull('deleted_at'),
                $this->twoLevelRule($categoryId),
            ],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image' => ['nullable', 'image', 'max:4096'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'remove_image' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'is_indexable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Categories are exactly two levels deep: the chosen parent must be a root,
     * and a category that already has children cannot itself become a child.
     */
    protected function twoLevelRule(?int $categoryId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($categoryId): void {
            if (blank($value)) {
                return;
            }

            if ($categoryId !== null && (int) $value === $categoryId) {
                $fail('لا يمكن أن يكون التصنيف أباً لنفسه.');

                return;
            }

            $parent = CourseCategory::query()->find((int) $value);
            if ($parent && ! $parent->isRoot()) {
                $fail('يجب أن يكون التصنيف الأب تصنيفاً رئيسياً (مستويان فقط).');

                return;
            }

            if ($categoryId !== null && CourseCategory::query()->where('parent_id', $categoryId)->exists()) {
                $fail('لا يمكن جعل هذا التصنيف فرعياً لأنه يحتوي على تصنيفات فرعية.');
            }
        };
    }

    protected function categoryId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم التصنيف',
            'slug' => 'المسار',
            'description' => 'الوصف',
            'parent_id' => 'التصنيف الأب',
            'icon' => 'الأيقونة',
            'color' => 'اللون',
            'image' => 'الصورة',
            'image_url' => 'رابط الصورة',
            'meta_title' => 'عنوان الميتا',
            'meta_description' => 'وصف الميتا',
            'meta_keywords' => 'الكلمات المفتاحية',
            'og_image' => 'صورة المشاركة',
            'order' => 'الترتيب',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'يجب أن يكون اللون بصيغة hex مثل #2563eb.',
        ];
    }
}
