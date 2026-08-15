<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $post->toPublicArray());

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'featured' => BlogPost::query()
                ->published()
                ->featured()
                ->with(['category:id,name,slug'])
                ->latest('published_at')
                ->limit(3)
                ->get()
                ->map(fn (BlogPost $post) => $post->toPublicArray())
                ->values()
                ->all(),
            'categories' => BlogCategory::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()
                ->map(fn (BlogCategory $c) => $c->toPublicArray())
                ->values()
                ->all(),
            'seo' => [
                'title' => 'المدونة — '.config('app.name'),
                'description' => 'آخر المقالات والتدوينات',
            ],
        ]);
    }

    public function search(Request $request): Response
    {
        $q = trim((string) $request->string('q'));

        $posts = BlogPost::query()
            ->published()
            ->when($q !== '', fn ($builder) => $builder->search($q))
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $post->toPublicArray());

        return Inertia::render('Blog/Search', [
            'posts' => $posts,
            'q' => $q,
            'seo' => [
                'title' => ($q !== '' ? 'بحث: '.$q.' — ' : 'بحث — ').config('app.name'),
                'description' => 'نتائج البحث في المدونة',
            ],
        ]);
    }

    public function category(string $slug): Response
    {
        $category = BlogCategory::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $posts = BlogPost::query()
            ->published()
            ->where('blog_category_id', $category->id)
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $post->toPublicArray());

        return Inertia::render('Blog/Category', [
            'category' => $category->toPublicArray(),
            'posts' => $posts,
            'seo' => [
                'title' => ($category->meta_title ?: $category->name).' — '.config('app.name'),
                'description' => $category->meta_description ?: $category->description,
            ],
        ]);
    }

    public function tag(string $slug): Response
    {
        $tag = BlogTag::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $posts = $tag->publishedPosts()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (BlogPost $post) => $post->toPublicArray());

        return Inertia::render('Blog/Tag', [
            'tag' => $tag->toPublicArray(),
            'posts' => $posts,
            'seo' => [
                'title' => ($tag->meta_title ?: $tag->name).' — '.config('app.name'),
                'description' => $tag->meta_description ?: $tag->description,
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $post = BlogPost::query()
            ->published()
            ->where('slug', $slug)
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->firstOrFail();

        $post->incrementViews();

        $related = BlogPost::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->when($post->blog_category_id, fn ($q) => $q->where('blog_category_id', $post->blog_category_id))
            ->with(['category:id,name,slug'])
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn (BlogPost $p) => $p->toPublicArray())
            ->values()
            ->all();

        return Inertia::render('Blog/Show', [
            'post' => $post->toPublicArray(true),
            'related' => $related,
            'seo' => $post->getSeoMetaTags(),
            'schema' => $post->getSchemaJsonLd(),
        ]);
    }
}
