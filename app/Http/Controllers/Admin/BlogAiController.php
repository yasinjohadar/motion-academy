<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Blog\BlogAiGenerateRequest;
use App\Http\Requests\Admin\Blog\BlogAiStoreRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\BlogAiGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BlogAiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.blog-ai.generate'),
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Blog/Ai/Create', [
            'categories' => BlogCategory::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogCategory $category) => $category->only(['id', 'name', 'slug']))
                ->values()
                ->all(),
            'tags' => BlogTag::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogTag $tag) => $tag->only(['id', 'name', 'slug']))
                ->values()
                ->all(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'ai_draft' => $request->session()->get('ai_draft'),
            ],
        ]);
    }

    public function generate(BlogAiGenerateRequest $request, BlogAiGenerator $generator): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payload = $generator->generate($data['topic'], [
                'content_length' => $data['content_length'],
                'tone' => $data['tone'],
                'language' => $data['language'],
                'category_id' => $data['category_id'] ?? null,
                'generate_seo' => (bool) ($data['generate_seo'] ?? true),
                'generate_og' => (bool) ($data['generate_og'] ?? true),
                'generate_twitter' => (bool) ($data['generate_twitter'] ?? true),
                'generate_schema' => (bool) ($data['generate_schema'] ?? true),
            ]);

            if (! empty($data['category_id'])) {
                $payload['blog_category_id'] = (int) $data['category_id'];
            }

            return back()
                ->with('ai_draft', $payload)
                ->with('success', 'تم توليد مسودة التدوينة بنجاح.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function store(BlogAiStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])));
        unset($data['tag_ids']);

        $slug = filled($data['slug'] ?? null)
            ? BlogCategory::slugify((string) $data['slug'])
            : BlogCategory::slugify((string) $data['title']);

        $post = BlogPost::query()->create([
            ...$data,
            'slug' => BlogPost::uniqueSlug($slug),
            'author_id' => $request->user()->id,
            'status' => 'draft',
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_indexable' => (bool) ($data['is_indexable'] ?? true),
            'is_followable' => (bool) ($data['is_followable'] ?? true),
            'language' => $data['language'] ?? 'ar',
            'robots_meta' => $data['robots_meta'] ?? 'index,follow',
            'priority' => (int) ($data['priority'] ?? 0),
            'order' => (int) ($data['order'] ?? 0),
        ]);

        $post->tags()->sync($tagIds);

        if ($post->blog_category_id) {
            $category = BlogCategory::query()->find($post->blog_category_id);
            $category?->update(['posts_count' => $category->posts()->count()]);
        }

        foreach ($tagIds as $tagId) {
            $tag = BlogTag::query()->find($tagId);
            $tag?->update(['posts_count' => $tag->posts()->count()]);
        }

        $request->session()->forget('ai_draft');

        return redirect()
            ->route('admin.blog-posts.edit', $post)
            ->with('success', 'تم حفظ مسودة التدوينة بنجاح.');
    }
}
