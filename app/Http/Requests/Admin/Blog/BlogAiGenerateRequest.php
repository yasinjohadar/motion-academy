<?php

namespace App\Http\Requests\Admin\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogAiGenerateRequest extends FormRequest
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
            'topic' => ['required', 'string', 'max:4000'],
            'content_length' => ['required', 'string', Rule::in(['short', 'medium', 'long'])],
            'tone' => ['required', 'string', Rule::in(['professional', 'friendly', 'technical', 'casual', 'formal'])],
            'language' => ['required', 'string', Rule::in(['ar', 'en'])],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'generate_seo' => ['sometimes', 'boolean'],
            'generate_og' => ['sometimes', 'boolean'],
            'generate_twitter' => ['sometimes', 'boolean'],
            'generate_schema' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'topic' => 'الموضوع',
            'content_length' => 'طول المحتوى',
            'tone' => 'النبرة',
            'language' => 'اللغة',
            'category_id' => 'التصنيف',
        ];
    }
}
