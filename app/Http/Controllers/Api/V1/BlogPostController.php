<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Published blog posts, for an external frontend.
 *
 * Read-only and unauthenticated by design: this serves public articles that are
 * already readable on the site itself, so a token would protect nothing. The rest of
 * the application is an Inertia admin panel and needs no REST surface at all.
 *
 * `published()` is the only thing standing between a draft and the internet, so every
 * query in this class starts from it.
 */
class BlogPostController extends Controller
{
    private const RELATIONS = ['author:id,name', 'category', 'tags'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->integer('per_page', 12), 1), 50);

        $posts = BlogPost::query()
            ->published()
            // Eager-loaded because the resource reads author, category and tags for
            // every row — without this a page of 12 posts is 37 queries.
            ->with(self::RELATIONS)
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($c) => $c->where('slug', $request->string('category')->toString())
            ))
            ->when($request->filled('tag'), fn ($q) => $q->whereHas(
                'tags',
                fn ($t) => $t->where('slug', $request->string('tag')->toString())
            ))
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->boolean('featured'), fn ($q) => $q->featured())
            ->latest('published_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return BlogPostResource::collection($posts);
    }

    /**
     * A single post by slug, with the article body.
     *
     * Bound by slug rather than id, and scoped to published: an unpublished draft must
     * answer 404, not 403 — its existence is not public information.
     */
    public function show(string $slug): JsonResource
    {
        $post = BlogPost::query()
            ->published()
            ->with(self::RELATIONS)
            ->where('slug', $slug)
            ->firstOrFail();

        return BlogPostResource::withContent($post);
    }
}
