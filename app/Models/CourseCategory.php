<?php

namespace App\Models;

use App\Services\Storage\AppStorageManager;
use Database\Factories\CourseCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A two-level course category: a root category, or a child of a root.
 * The depth limit is enforced by the form requests, not the schema.
 */
class CourseCategory extends Model
{
    /** @use HasFactory<CourseCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Logical storage disk for uploaded category images. */
    public const STORAGE_DISK = 'courses';

    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color', 'image', 'parent_id',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'is_indexable',
        'is_active', 'is_featured', 'order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_indexable' => 'boolean',
            'order' => 'integer',
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
        return BlogCategory::slugify($value);
    }

    /**
     * Slugs stay unique across trashed rows too, because the column index does.
     */
    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'category';
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

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order')->orderBy('name');
    }

    /** @return HasMany<Course, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'course_category_id');
    }

    /** @return HasMany<Course, $this> */
    public function publishedCourses(): HasMany
    {
        return $this->courses()->published();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Icon classes for the public site, which loads both Font Awesome and Remix Icon.
     */
    public function publicIcon(): string
    {
        $icon = (string) $this->icon;

        return match (true) {
            $icon === '' => 'fas fa-folder-open',
            str_starts_with($icon, 'ri-'), str_contains($icon, ' ') => $icon,
            str_starts_with($icon, 'fa-') => 'fas '.$icon,
            default => $icon,
        };
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function hasUploadedImage(): bool
    {
        return filled($this->image) && ! Str::startsWith((string) $this->image, ['http://', 'https://']);
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image)) {
            return null;
        }

        if (! $this->hasUploadedImage()) {
            return $this->image;
        }

        try {
            return app(AppStorageManager::class)->url(self::STORAGE_DISK, $this->image)
                ?? asset('storage/'.$this->image);
        } catch (\Throwable) {
            return asset('storage/'.$this->image);
        }
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
            'description_excerpt' => Str::limit(trim(html_entity_decode(strip_tags((string) $this->description))), 120),
            'icon' => $this->icon,
            'color' => $this->color,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'parent_id' => $this->parent_id,
            'parent' => $this->relationLoaded('parent') ? $this->parent?->only(['id', 'name']) : null,
            'children_count' => $this->children_count ?? null,
            'courses_count' => $this->courses_count ?? null,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_image' => $this->og_image,
            'is_indexable' => $this->is_indexable ?? true,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'order' => $this->order,
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
            'deleted_at' => optional($this->deleted_at)?->format('Y-m-d H:i'),
        ];
    }
}
