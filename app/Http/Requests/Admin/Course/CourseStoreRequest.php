<?php

namespace App\Http\Requests\Admin\Course;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\VideoSource;
use App\Models\Course;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseStoreRequest extends FormRequest
{
    /** Roles allowed to be set as a course's instructor: accounts are admins or students. */
    public const INSTRUCTOR_ROLES = ['admin'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop empty rows from the list fields before validating them.
     */
    protected function prepareForValidation(): void
    {
        foreach (['learning_outcomes', 'requirements', 'target_audience'] as $field) {
            if (is_array($this->input($field))) {
                $this->merge([
                    $field => array_values(array_filter(
                        array_map(fn ($item) => is_string($item) ? trim($item) : $item, $this->input($field)),
                        fn ($item) => filled($item),
                    )),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseId = $this->courseId();
        $introSource = $this->input('intro_video_source');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('courses', 'slug')->ignore($courseId)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:5000', $this->plainTextMaxRule(500)],
            'description' => ['nullable', 'string', 'max:200000'],

            'course_category_id' => [
                'required',
                'integer',
                Rule::exists('course_categories', 'id')->whereNull('deleted_at'),
            ],
            'instructor_id' => ['nullable', 'integer', 'exists:users,id', $this->instructorRule()],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'language' => ['required', 'string', Rule::in(['ar', 'en', 'fr', 'tr'])],

            'status' => ['required', Rule::enum(CourseStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],

            'is_free' => ['sometimes', 'boolean'],
            'price' => ['exclude_if:is_free,true', 'exclude_if:is_free,1', 'required', 'numeric', 'min:0', 'max:99999999'],
            'sale_price' => ['exclude_if:is_free,true', 'exclude_if:is_free,1', 'nullable', 'numeric', 'min:0', 'lt:price'],
            'sale_ends_at' => ['exclude_if:is_free,true', 'exclude_if:is_free,1', 'nullable', 'date', 'after:now'],
            'currency' => ['required', 'string', 'size:3'],

            'thumbnail' => ['nullable', 'image', 'max:5120'],
            'thumbnail_url' => ['nullable', 'string', 'max:500'],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'is_indexable' => ['sometimes', 'boolean'],

            'intro_video_source' => ['nullable', Rule::enum(VideoSource::class)],
            'intro_video' => [
                'nullable',
                'string',
                'max:500',
                Rule::when(in_array($introSource, ['youtube', 'vimeo', 'url'], true), ['url']),
                $this->videoPatternRule($introSource),
            ],
            'intro_video_file' => [
                'nullable',
                Rule::requiredIf(fn () => $introSource === VideoSource::Upload->value
                    && ! $this->hasExistingUploadedIntro()),
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.(int) config('media.max_video_upload_kb', 512000),
            ],

            'learning_outcomes' => ['nullable', 'array', 'max:30'],
            'learning_outcomes.*' => ['string', 'max:300'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['string', 'max:300'],
            'target_audience' => ['nullable', 'array', 'max:30'],
            'target_audience.*' => ['string', 'max:300'],

            'has_certificate' => ['sometimes', 'boolean'],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:1000000'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A course cannot be published empty-handed: it needs published lessons and a
     * description to sell it. New courses therefore start as drafts.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') !== CourseStatus::Published->value) {
                    return;
                }

                if ($this->publishedLessonsCount() === 0) {
                    $validator->errors()->add('status', 'لا يمكن نشر دورة بدون دروس منشورة. احفظها كمسودة ثم أضف المنهج.');
                }

                if (blank(strip_tags((string) $this->input('short_description')))
                    && blank(strip_tags((string) $this->input('description')))) {
                    $validator->errors()->add('short_description', 'أضف وصفاً مختصراً أو تفصيلياً قبل نشر الدورة.');
                }
            },
        ];
    }

    protected function instructorRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }

            // An existing course keeps its instructor even if that account is no
            // longer an admin, so older courses can still be edited.
            $course = $this->route('course');
            if ($course instanceof Course && (int) $value === (int) $course->instructor_id) {
                return;
            }

            $isInstructor = User::query()
                ->whereKey((int) $value)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', self::INSTRUCTOR_ROLES))
                ->exists();

            if (! $isInstructor) {
                $fail('المدرّس المختار يجب أن يكون حساب أدمن.');
            }
        };
    }

    /**
     * Limits the visible text, not the HTML markup around it.
     */
    protected function plainTextMaxRule(int $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($max): void {
            $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($text) > $max) {
                $fail("يجب ألا يتجاوز :attribute {$max} حرف (النص دون التنسيق).");
            }
        };
    }

    protected function videoPatternRule(?string $source): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($source): void {
            $pattern = VideoSource::tryFrom((string) $source)?->urlPattern();
            if (filled($value) && $pattern !== null && ! preg_match($pattern, (string) $value)) {
                $fail('رابط الفيديو لا يطابق المصدر المختار.');
            }
        };
    }

    protected function courseId(): ?int
    {
        return null;
    }

    protected function publishedLessonsCount(): int
    {
        return 0;
    }

    protected function hasExistingUploadedIntro(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان الدورة',
            'slug' => 'المسار',
            'subtitle' => 'العنوان الفرعي',
            'short_description' => 'الوصف المختصر',
            'description' => 'الوصف التفصيلي',
            'course_category_id' => 'التصنيف',
            'instructor_id' => 'المدرّس',
            'level' => 'المستوى',
            'language' => 'اللغة',
            'status' => 'الحالة',
            'published_at' => 'تاريخ النشر',
            'price' => 'السعر',
            'sale_price' => 'سعر الخصم',
            'sale_ends_at' => 'انتهاء الخصم',
            'currency' => 'العملة',
            'thumbnail' => 'صورة الدورة',
            'intro_video' => 'رابط الفيديو التعريفي',
            'intro_video_file' => 'ملف الفيديو التعريفي',
            'learning_outcomes.*' => 'عنصر "ماذا ستتعلم"',
            'requirements.*' => 'عنصر المتطلبات',
            'target_audience.*' => 'عنصر الفئة المستهدفة',
            'max_students' => 'الحد الأقصى للطلاب',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_price.lt' => 'يجب أن يكون سعر الخصم أقل من السعر الأصلي.',
            'sale_ends_at.after' => 'يجب أن يكون تاريخ انتهاء الخصم في المستقبل.',
            'intro_video_file.required' => 'اختر ملف الفيديو التعريفي.',
        ];
    }
}
