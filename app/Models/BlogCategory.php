<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color', 'image', 'parent_id',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'og_title', 'og_description', 'og_image', 'schema_data',
        'is_active', 'is_featured', 'order', 'posts_count',
        'is_indexable', 'robots_meta',
    ];

    protected function casts(): array
    {
        return [
            'schema_data' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_indexable' => 'boolean',
            'order' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if (blank($category->slug) && filled($category->name)) {
                $category->slug = static::uniqueSlug(static::slugify($category->name), $category->id);
            }
        });
    }

    public static function slugify(string $value): string
    {
        $slug = preg_replace('/\s+/', '-', trim($value));
        $slug = preg_replace('/[^\p{Arabic}a-zA-Z0-9-]/u', '', (string) $slug);
        $slug = trim((string) preg_replace('/-+/', '-', (string) $slug), '-');

        return $slug !== '' ? Str::lower($slug) : 'category';
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'category';
        $original = $slug;
        $i = 2;

        while (
            static::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $original.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'blog_category_id');
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->published();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'image' => $this->image,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent?->only(['id', 'name']),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'order' => $this->order,
            'posts_count' => $this->posts_count,
            'is_indexable' => $this->is_indexable,
            'robots_meta' => $this->robots_meta,
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'posts_count' => $this->posts_count,
            'meta_title' => $this->meta_title ?: $this->name,
            'meta_description' => $this->meta_description ?: $this->description,
        ];
    }
}
