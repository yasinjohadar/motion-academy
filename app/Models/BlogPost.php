<?php

namespace App\Models;

use App\Models\Concerns\ExposesPolicyFlags;
use App\Services\Storage\AppStorageManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use ExposesPolicyFlags, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image', 'featured_image_alt',
        'author_id', 'blog_category_id',
        'meta_title', 'meta_description', 'meta_keywords',
        'canonical_url', 'focus_keyword', 'focus_keyword_synonyms',
        'og_title', 'og_description', 'og_image', 'og_type', 'og_locale',
        'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image', 'twitter_creator',
        'schema_type', 'schema_headline', 'schema_description', 'schema_image',
        'schema_published_time', 'schema_modified_time', 'schema_author_name', 'schema_author_url',
        'breadcrumb_schema',
        'reading_time', 'views_count', 'shares_count', 'comments_count',
        'status', 'published_at', 'scheduled_at',
        'is_indexable', 'is_followable', 'robots_meta',
        'is_featured', 'priority', 'order',
        'seo_score', 'readability_score', 'keyword_density',
        'related_posts', 'utm_source', 'utm_medium', 'utm_campaign',
        'custom_meta', 'language', 'translation_group_id',
    ];

    protected function casts(): array
    {
        return [
            'breadcrumb_schema' => 'array',
            'related_posts' => 'array',
            'custom_meta' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'schema_published_time' => 'datetime',
            'schema_modified_time' => 'datetime',
            'is_indexable' => 'boolean',
            'is_followable' => 'boolean',
            'is_featured' => 'boolean',
            'views_count' => 'integer',
            'shares_count' => 'integer',
            'comments_count' => 'integer',
            'reading_time' => 'integer',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
            'keyword_density' => 'integer',
            'priority' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            if (blank($post->slug) && filled($post->title)) {
                $post->slug = static::uniqueSlug(BlogCategory::slugify($post->title), $post->id);
            }

            if (filled($post->content) && blank($post->reading_time)) {
                $words = str_word_count(strip_tags((string) $post->content));
                $post->reading_time = max(1, (int) ceil($words / 200));
            }

            if ($post->status === 'published' && blank($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'post';
        $original = $slug;
        $i = 2;

        while (
            static::withTrashed()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'blog_post_id', 'blog_tag_id')
            ->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('content', 'like', $like)
                ->orWhere('meta_keywords', 'like', $like);
        });
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function featuredImageUrl(): ?string
    {
        if (! filled($this->featured_image)) {
            return null;
        }

        if (Str::startsWith($this->featured_image, ['http://', 'https://'])) {
            return $this->featured_image;
        }

        try {
            return app(AppStorageManager::class)->url('blog', $this->featured_image)
                ?? asset('storage/'.$this->featured_image);
        } catch (\Throwable) {
            return asset('storage/'.$this->featured_image);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSeoMetaTags(): array
    {
        $image = $this->og_image ?: $this->featuredImageUrl();

        return [
            'title' => $this->meta_title ?: $this->title,
            'description' => $this->meta_description ?: $this->excerpt,
            'keywords' => $this->meta_keywords,
            'canonical' => $this->canonical_url ?: url('/blog/'.$this->slug),
            'og:title' => $this->og_title ?: $this->title,
            'og:description' => $this->og_description ?: $this->excerpt,
            'og:image' => $image,
            'og:type' => $this->og_type ?: 'article',
            'og:locale' => $this->og_locale ?: 'ar_SA',
            'twitter:card' => $this->twitter_card ?: 'summary_large_image',
            'twitter:title' => $this->twitter_title ?: $this->title,
            'twitter:description' => $this->twitter_description ?: $this->excerpt,
            'twitter:image' => $this->twitter_image ?: $image,
            'robots' => $this->robots_meta ?: 'index,follow',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchemaJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $this->schema_type ?: 'BlogPosting',
            'headline' => $this->schema_headline ?: $this->title,
            'description' => $this->schema_description ?: $this->excerpt,
            'image' => $this->schema_image ?: $this->featuredImageUrl(),
            'datePublished' => optional($this->schema_published_time ?: $this->published_at)?->toIso8601String(),
            'dateModified' => optional($this->schema_modified_time ?: $this->updated_at)?->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $this->schema_author_name ?: $this->author?->name,
                'url' => $this->schema_author_url,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
            ],
            'mainEntityOfPage' => url('/blog/'.$this->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(bool $withContent = true): array
    {
        $data = [
            'can' => $this->policyFlags(),
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->featuredImageUrl(),
            'featured_image_alt' => $this->featured_image_alt,
            'author_id' => $this->author_id,
            'author' => $this->author?->only(['id', 'name']),
            'blog_category_id' => $this->blog_category_id,
            'category' => $this->category?->only(['id', 'name', 'slug']),
            'tag_ids' => $this->relationLoaded('tags')
                ? $this->tags->pluck('id')->values()->all()
                : $this->tags()->pluck('blog_tags.id')->all(),
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map->only(['id', 'name', 'slug'])->values()->all()
                : [],
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $this->canonical_url,
            'focus_keyword' => $this->focus_keyword,
            'focus_keyword_synonyms' => $this->focus_keyword_synonyms,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'og_type' => $this->og_type,
            'og_locale' => $this->og_locale,
            'twitter_card' => $this->twitter_card,
            'twitter_title' => $this->twitter_title,
            'twitter_description' => $this->twitter_description,
            'twitter_image' => $this->twitter_image,
            'twitter_creator' => $this->twitter_creator,
            'schema_type' => $this->schema_type,
            'schema_headline' => $this->schema_headline,
            'schema_description' => $this->schema_description,
            'schema_image' => $this->schema_image,
            'schema_author_name' => $this->schema_author_name,
            'schema_author_url' => $this->schema_author_url,
            'reading_time' => $this->reading_time,
            'views_count' => $this->views_count,
            'status' => $this->status,
            'published_at' => optional($this->published_at)?->format('Y-m-d\TH:i'),
            'scheduled_at' => optional($this->scheduled_at)?->format('Y-m-d\TH:i'),
            'is_indexable' => $this->is_indexable,
            'is_followable' => $this->is_followable,
            'robots_meta' => $this->robots_meta,
            'is_featured' => $this->is_featured,
            'priority' => $this->priority,
            'order' => $this->order,
            'language' => $this->language,
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
            'updated_at' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];

        if ($withContent) {
            $data['content'] = $this->content;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(bool $withContent = false): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $withContent ? $this->content : null,
            'featured_image_url' => $this->featuredImageUrl(),
            'featured_image_alt' => $this->featured_image_alt,
            'author' => $this->author?->only(['id', 'name']),
            'category' => $this->category?->toPublicArray(),
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map->toPublicArray()->values()->all()
                : [],
            'reading_time' => $this->reading_time,
            'views_count' => $this->views_count,
            'is_featured' => $this->is_featured,
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'published_at_human' => optional($this->published_at)?->format('Y-m-d'),
            'seo' => $this->getSeoMetaTags(),
            'schema' => $withContent ? $this->getSchemaJsonLd() : null,
        ];
    }
}
