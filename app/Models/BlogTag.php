<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'color',
        'meta_title', 'meta_description', 'canonical_url',
        'is_active', 'posts_count', 'order', 'is_indexable',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_indexable' => 'boolean',
            'posts_count' => 'integer',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $tag) {
            if (blank($tag->slug) && filled($tag->name)) {
                $tag->slug = static::uniqueSlug(BlogCategory::slugify($tag->name), $tag->id);
            }
        });
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'tag';
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

    /** @return BelongsToMany<BlogPost, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'blog_tag_id', 'blog_post_id')
            ->withTimestamps();
    }

    public function publishedPosts(): BelongsToMany
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
            'color' => $this->color,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'is_active' => $this->is_active,
            'posts_count' => $this->posts_count,
            'order' => $this->order,
            'is_indexable' => $this->is_indexable,
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
            'posts_count' => $this->posts_count,
            'meta_title' => $this->meta_title ?: $this->name,
            'meta_description' => $this->meta_description ?: $this->description,
        ];
    }
}
