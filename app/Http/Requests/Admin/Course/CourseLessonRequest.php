<?php

namespace App\Http\Requests\Admin\Course;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Models\CourseLesson;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lesson fields are conditional on the lesson type and, for videos, on the source:
 * a YouTube/Vimeo/direct link needs `video`, an upload needs `video_file` (unless one
 * is already stored), a text lesson needs `content`, a file lesson needs an attachment.
 */
class CourseLessonRequest extends FormRequest
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
        $type = $this->input('type');
        $source = $this->input('video_source');
        $isVideo = $type === LessonType::Video->value;
        $isLinkVideo = $isVideo && in_array($source, ['youtube', 'vimeo', 'url'], true);
        $isUploadVideo = $isVideo && $source === VideoSource::Upload->value;
        $lesson = $this->lesson();

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(LessonType::class)],

            'video_source' => [Rule::requiredIf($isVideo), 'nullable', Rule::enum(VideoSource::class)],
            'video' => [
                Rule::requiredIf($isLinkVideo),
                'nullable',
                'string',
                'max:500',
                Rule::when($isLinkVideo, ['url']),
                $this->videoPatternRule($source),
            ],
            'video_file' => [
                Rule::requiredIf($isUploadVideo && ! $lesson?->hasStoredVideo()),
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.(int) config('media.max_video_upload_kb', 512000),
            ],

            'content' => [Rule::requiredIf($type === LessonType::Text->value), 'nullable', 'string', 'max:500000'],

            'attachment_file' => [
                Rule::requiredIf($type === LessonType::File->value
                    && blank($this->input('attachment'))
                    && ! filled($lesson?->attachment)),
                'nullable',
                'file',
                'mimes:pdf,zip,rar,doc,docx,ppt,pptx,xls,xlsx,txt,psd,ai,aep,prproj',
                'max:'.(int) config('media.max_attachment_upload_kb', 51200),
            ],
            'attachment' => ['nullable', 'url', 'max:500'],
            'remove_attachment' => ['sometimes', 'boolean'],

            // Extra resources: named links and files, for any lesson type.
            'attachments' => ['nullable', 'array', 'max:30'],
            'attachments.*.id' => ['nullable', 'integer'],
            'attachments.*.type' => ['required', Rule::in(['link', 'file'])],
            'attachments.*.title' => ['required', 'string', 'max:150'],
            'attachments.*.url' => ['nullable', 'required_if:attachments.*.type,link', 'url', 'max:1000'],
            'attachments.*.file' => [
                'nullable',
                'file',
                'mimes:pdf,zip,rar,7z,doc,docx,ppt,pptx,xls,xlsx,csv,txt,psd,ai,aep,prproj,fig,png,jpg,jpeg,gif,webp,svg,mp3,wav',
                'max:'.(int) config('media.max_attachment_upload_kb', 51200),
            ],

            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'is_preview' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'course_section_id' => ['sometimes', 'integer'],
        ];
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

    private function lesson(): ?CourseLesson
    {
        $lesson = $this->route('lesson');

        return $lesson instanceof CourseLesson ? $lesson : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان الدرس',
            'type' => 'نوع الدرس',
            'video_source' => 'مصدر الفيديو',
            'video' => 'رابط الفيديو',
            'video_file' => 'ملف الفيديو',
            'content' => 'محتوى الدرس',
            'attachment_file' => 'الملف المرفق',
            'attachment' => 'رابط المرفق',
            'duration_seconds' => 'مدة الدرس',
            'attachments.*.title' => 'اسم المرفق',
            'attachments.*.url' => 'رابط المرفق',
            'attachments.*.file' => 'ملف المرفق',
        ];
    }

    /**
     * A new file row needs its upload; an existing one may keep the stored file.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ((array) $this->input('attachments', []) as $index => $row) {
                    if (($row['type'] ?? null) === 'file' && blank($row['id'] ?? null) && ! $this->hasFile("attachments.{$index}.file")) {
                        $validator->errors()->add("attachments.{$index}.file", 'اختر ملفاً لهذا المرفق.');
                    }
                }
            },
        ];
    }
}
