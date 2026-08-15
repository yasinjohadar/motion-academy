<?php

namespace App\Http\Requests\Admin\Blog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogTagUpdateRequest extends FormRequest
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
        $tagId = $this->route('blog_tag')?->id ?? $this->route('blog_tag');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blog_tags', 'slug')->ignore($tagId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:32'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_indexable' => ['sometimes', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'اسم الوسم',
            'slug' => 'المسار',
            'description' => 'الوصف',
            'color' => 'اللون',
        ];
    }
}
