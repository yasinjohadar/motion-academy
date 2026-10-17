<?php

namespace App\Http\Requests\Admin\Course;

use App\Models\CourseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseCategoryReorderRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_categories', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Only siblings can be reordered together.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $parentCount = CourseCategory::query()
                    ->whereIn('id', $this->input('ids', []))
                    ->pluck('parent_id')
                    ->unique()
                    ->count();

                if ($parentCount > 1) {
                    $validator->errors()->add('ids', 'يمكن إعادة ترتيب التصنيفات داخل نفس المستوى فقط.');
                }
            },
        ];
    }
}
