<?php

namespace App\Http\Requests\Admin\Course;

use App\Models\CourseLanding;
use App\Support\SocialPlatforms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseLandingRequest extends FormRequest
{
    private const YOUTUBE_MESSAGE = 'يجب أن يكون رابط فيديو يوتيوب صالحاً.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rows the editor left completely blank are dropped before validation, so an
     * unused "add" click never blocks saving.
     */
    protected function prepareForValidation(): void
    {
        $filled = fn (array $keys) => fn ($row) => is_array($row)
            && collect($keys)->contains(fn (string $key) => filled($row[$key] ?? null));

        $this->merge([
            'videos' => array_values(array_filter((array) $this->input('videos', []), $filled(['title', 'url']))),
            'goals' => array_values(array_filter((array) $this->input('goals', []), fn ($goal) => filled($goal))),
            'features' => array_values(array_filter((array) $this->input('features', []), $filled(['title', 'text']))),
            'testimonials' => array_values(array_filter((array) $this->input('testimonials', []), $filled(['name', 'role', 'text', 'video_url']))),
            'faqs' => array_values(array_filter((array) $this->input('faqs', []), $filled(['question', 'answer']))),
            'social_links' => array_values(array_filter((array) $this->input('social_links', []), $filled(['url']))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_published' => ['required', 'boolean'],
            'tagline' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:100000'],

            'videos' => ['array', 'max:12'],
            'videos.*.title' => ['nullable', 'string', 'max:150'],
            'videos.*.url' => ['required', 'string', 'max:500'],

            'goals' => ['array', 'max:20'],
            'goals.*' => ['string', 'max:250'],

            'features' => ['array', 'max:12'],
            'features.*.title' => ['required', 'string', 'max:120'],
            'features.*.text' => ['nullable', 'string', 'max:400'],

            'testimonials' => ['array', 'max:20'],
            'testimonials.*.name' => ['required', 'string', 'max:120'],
            'testimonials.*.role' => ['nullable', 'string', 'max:120'],
            'testimonials.*.text' => ['nullable', 'required_without:testimonials.*.video_url', 'string', 'max:1000'],
            'testimonials.*.video_url' => ['nullable', 'string', 'max:500'],

            'faqs' => ['array', 'max:20'],
            'faqs.*.question' => ['required', 'string', 'max:200'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],

            'social_links' => ['array', 'max:10'],
            'social_links.*.platform' => ['required', Rule::in(SocialPlatforms::keys())],
            'social_links.*.url' => ['required', 'string', 'max:500'],

            'show_price' => ['required', 'boolean'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|#).+/i'],

            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'allow_indexing' => ['required', 'boolean'],
        ];
    }

    /**
     * Video links must be YouTube; social links must be real URLs.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('videos', []) as $index => $video) {
                    if (filled($video['url'] ?? null) && ! CourseLanding::youtubeId($video['url'])) {
                        $validator->errors()->add("videos.{$index}.url", self::YOUTUBE_MESSAGE);
                    }
                }

                foreach ((array) $this->input('testimonials', []) as $index => $item) {
                    if (filled($item['video_url'] ?? null) && ! CourseLanding::youtubeId($item['video_url'])) {
                        $validator->errors()->add("testimonials.{$index}.video_url", self::YOUTUBE_MESSAGE);
                    }
                }

                SocialPlatforms::validateUrls($validator, $this->input('social_links', []), 'social_links');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tagline' => 'العبارة التسويقية',
            'description' => 'الوصف',
            'videos.*.url' => 'رابط الفيديو',
            'videos.*.title' => 'عنوان الفيديو',
            'goals.*' => 'الهدف',
            'features.*.title' => 'عنوان الميزة',
            'features.*.text' => 'وصف الميزة',
            'testimonials.*.name' => 'اسم المتدرب',
            'testimonials.*.text' => 'نص الرأي',
            'testimonials.*.video_url' => 'رابط فيديو الرأي',
            'faqs.*.question' => 'السؤال',
            'faqs.*.answer' => 'الجواب',
            'social_links.*.platform' => 'المنصة',
            'social_links.*.url' => 'الرابط',
            'cta_label' => 'نص الزر',
            'cta_url' => 'رابط الزر',
            'meta_title' => 'عنوان SEO',
            'meta_description' => 'وصف SEO',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'testimonials.*.text.required_without' => 'أضف نص الرأي أو رابط فيديو له.',
        ];
    }
}
