<?php

namespace App\Http\Requests\Admin\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostStoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('blog_posts', 'slug')],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'content' => ['required', 'string'],
            'blog_category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:blog_tags,id'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'featured_image' => ['nullable', 'image', 'max:5120'],
            'featured_image_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/).+/i'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'focus_keyword' => ['nullable', 'string', 'max:255'],
            'focus_keyword_synonyms' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'og_type' => ['nullable', 'string', 'max:32'],
            'og_locale' => ['nullable', 'string', 'max:16'],
            'twitter_card' => ['nullable', 'string', 'max:64'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string', 'max:1000'],
            'twitter_image' => ['nullable', 'string', 'max:500'],
            'twitter_creator' => ['nullable', 'string', 'max:255'],
            'schema_type' => ['nullable', 'string', 'max:32'],
            'schema_headline' => ['nullable', 'string', 'max:500'],
            'schema_description' => ['nullable', 'string', 'max:2000'],
            'schema_image' => ['nullable', 'string', 'max:500'],
            'schema_author_name' => ['nullable', 'string', 'max:255'],
            'schema_author_url' => ['nullable', 'string', 'max:500'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_indexable' => ['sometimes', 'boolean'],
            'is_followable' => ['sometimes', 'boolean'],
            'language' => ['nullable', 'string', 'max:5'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'robots_meta' => ['nullable', 'string', 'max:32'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان المقال',
            'slug' => 'المسار',
            'content' => 'المحتوى',
            'blog_category_id' => 'التصنيف',
            'tag_ids' => 'الوسوم',
            'status' => 'الحالة',
            'featured_image' => 'الصورة البارزة',
        ];
    }
}
