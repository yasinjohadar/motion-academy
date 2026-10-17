<?php

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\VideoSource;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use App\Services\Storage\AppStorageManager;
use Carbon\CarbonInterface;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Enum, datetime and JSON casts are invisible to static analysis without these.
 *
 * @property CourseStatus $status
 * @property CourseLevel $level
 * @property VideoSource|null $intro_video_source
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $sale_ends_at
 * @property CarbonInterface|null $deleted_at
 * @property array<int, string>|null $learning_outcomes
 * @property array<int, string>|null $requirements
 * @property array<int, string>|null $target_audience
 */
class Course extends Model
{
    use AuditsChanges;
    use ExposesPolicyFlags;

    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Logical disk for the thumbnail and the uploaded intro video. */
    public const IMAGE_DISK = 'courses';

    /** Private logical disk for uploaded lesson videos, attachments and intro videos. */
    public const VIDEO_DISK = 'course-videos';

    protected $fillable = [
        'course_category_id', 'instructor_id', 'created_by',
        'title', 'slug', 'subtitle', 'short_description', 'description',
        'thumbnail', 'intro_video_source', 'intro_video',
        'level', 'language', 'status', 'published_at', 'is_featured',
        'is_free', 'price', 'sale_price', 'sale_ends_at', 'currency',
        'learning_outcomes', 'requirements', 'target_audience',
        'has_certificate', 'max_students',
        'lessons_count', 'duration_seconds',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image', 'is_indexable',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'level' => CourseLevel::class,
            'intro_video_source' => VideoSource::class,
            'published_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_indexable' => 'boolean',
            'is_free' => 'boolean',
            'has_certificate' => 'boolean',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'max_students' => 'integer',
            'lessons_count' => 'integer',
            'duration_seconds' => 'integer',
            'learning_outcomes' => 'array',
            'requirements' => 'array',
            'target_audience' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $course) {
            if (blank($course->slug) && filled($course->title)) {
                $course->slug = static::uniqueSlug(static::slugify($course->title), $course->id);
            }

            if ($course->status === CourseStatus::Published && $course->published_at === null) {
                $course->published_at = now();
            }

            if ($course->is_free) {
                $course->price = 0;
                $course->sale_price = null;
                $course->sale_ends_at = null;
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
        $slug = $base !== '' && $base !== 'category' ? $base : 'course';
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

    /** @return BelongsTo<CourseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return HasMany<CourseSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('order')->orderBy('id');
    }

    /** @return HasMany<CourseLesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class);
    }

    /** @return HasMany<CourseEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Whether the course has reached max_students (approved enrollments only).
     */
    public function isFull(): bool
    {
        return $this->max_students !== null
            && $this->enrollments()->where('status', CourseEnrollment::STATUS_APPROVED)->count() >= $this->max_students;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published)
            ->where(fn (Builder $inner) => $inner->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Uses the FULLTEXT index on MySQL/PostgreSQL; LIKE elsewhere (SQLite in dev).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true) && mb_strlen($term) >= 3) {
            $query->where(fn (Builder $inner) => $inner
                ->whereFullText(['title', 'short_description'], $term)
                ->orWhere('slug', 'like', "%{$term}%"));

            return;
        }

        $like = "%{$term}%";
        $query->where(fn (Builder $inner) => $inner
            ->where('title', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->orWhere('short_description', 'like', $like));
    }

    public function isOnSale(): bool
    {
        return ! $this->is_free
            && $this->sale_price !== null
            && (float) $this->sale_price < (float) $this->price
            && ($this->sale_ends_at === null || $this->sale_ends_at->isFuture());
    }

    public function effectivePrice(): float
    {
        if ($this->is_free) {
            return 0.0;
        }

        return (float) ($this->isOnSale() ? $this->sale_price : $this->price);
    }

    public function discountPercent(): ?int
    {
        if (! $this->isOnSale() || (float) $this->price <= 0) {
            return null;
        }

        return (int) round((1 - (float) $this->sale_price / (float) $this->price) * 100);
    }

    /** Symbols for the currencies offered in the admin form; others show their ISO code. */
    private const CURRENCY_SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'SAR' => 'ر.س', 'AED' => 'د.إ', 'EGP' => 'ج.م',
        'KWD' => 'د.ك', 'QAR' => 'ر.ق', 'JOD' => 'د.أ', 'TRY' => '₺',
    ];

    public function formatAmount(float $amount): string
    {
        $symbol = self::CURRENCY_SYMBOLS[$this->currency] ?? $this->currency;
        $number = fmod($amount, 1.0) === 0.0 ? number_format($amount) : number_format($amount, 2);

        return $symbol === '$' ? '$'.$number : $number.' '.$symbol;
    }

    /** What the student pays, as shown on the public site. */
    public function formattedPrice(): string
    {
        return $this->is_free ? 'مجاناً' : $this->formatAmount($this->effectivePrice());
    }

    /** The struck-through original price, only while a sale is running. */
    public function formattedOriginalPrice(): ?string
    {
        return $this->isOnSale() ? $this->formatAmount((float) $this->price) : null;
    }

    /** The ribbon on public course cards. */
    public function publicBadge(): ?string
    {
        return match (true) {
            $this->isOnSale() => 'خصم '.$this->discountPercent().'%',
            $this->is_free => 'مجانية',
            $this->is_featured => 'مميزة',
            default => null,
        };
    }

    public function formattedDuration(): string
    {
        return static::humanDuration($this->duration_seconds);
    }

    public static function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return match (true) {
            $hours > 0 && $minutes > 0 => "{$hours} س {$minutes} د",
            $hours > 0 => "{$hours} ساعة",
            $minutes > 0 => "{$minutes} دقيقة",
            default => "{$seconds} ثانية",
        };
    }

    public function languageLabel(): string
    {
        return match ($this->language) {
            'ar' => 'العربية',
            'en' => 'الإنجليزية',
            'fr' => 'الفرنسية',
            'tr' => 'التركية',
            default => (string) $this->language,
        };
    }

    public static function isStoredPath(?string $value): bool
    {
        return filled($value) && ! Str::startsWith((string) $value, ['http://', 'https://']);
    }

    public function thumbnailUrl(): ?string
    {
        return static::publicUrl($this->thumbnail);
    }

    public function introVideoUrl(): ?string
    {
        if (! filled($this->intro_video)) {
            return null;
        }

        return $this->intro_video_source === VideoSource::Upload
            ? app(AppStorageManager::class)->temporaryUrl(self::VIDEO_DISK, $this->intro_video, 120)
            : $this->intro_video;
    }

    public static function publicUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (! static::isStoredPath($value)) {
            return $value;
        }

        try {
            return app(AppStorageManager::class)->url(self::IMAGE_DISK, $value) ?? asset('storage/'.$value);
        } catch (\Throwable) {
            return asset('storage/'.$value);
        }
    }

    /**
     * Recount the published lessons and their total length.
     */
    public function refreshStats(): void
    {
        $published = $this->lessons()->where('is_published', true);

        $this->forceFill([
            'lessons_count' => (clone $published)->count(),
            'duration_seconds' => (int) (clone $published)->sum('duration_seconds'),
        ])->saveQuietly();
    }

    /**
     * Columns safe for the admin list; the full set is in toAdminArray().
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'thumbnail_url' => $this->thumbnailUrl(),
            'category' => $this->relationLoaded('category') ? $this->category?->only(['id', 'name']) : null,
            'instructor' => $this->relationLoaded('instructor') ? $this->instructor?->only(['id', 'name']) : null,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'is_featured' => $this->is_featured,
            'is_free' => $this->is_free,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'is_on_sale' => $this->isOnSale(),
            'effective_price' => $this->effectivePrice(),
            'discount_percent' => $this->discountPercent(),
            'currency' => $this->currency,
            'lessons_count' => $this->lessons_count,
            'duration_seconds' => $this->duration_seconds,
            'published_at' => $this->published_at?->format('Y-m-d H:i'),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i'),
            'can' => $this->policyFlags(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            ...$this->toListArray(),
            'course_category_id' => $this->course_category_id,
            'instructor_id' => $this->instructor_id,
            'subtitle' => $this->subtitle,
            'short_description' => $this->short_description,
            'short_description_excerpt' => Str::limit(trim(html_entity_decode(strip_tags((string) $this->short_description))), 160),
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'intro_video_source' => $this->intro_video_source?->value,
            'intro_video' => $this->intro_video,
            'intro_video_url' => $this->introVideoUrl(),
            'intro_video_embed' => $this->intro_video_source?->embedUrl($this->intro_video),
            'language' => $this->language,
            'sale_ends_at' => $this->sale_ends_at?->format('Y-m-d\TH:i'),
            'published_at_input' => $this->published_at?->format('Y-m-d\TH:i'),
            'learning_outcomes' => $this->learning_outcomes ?? [],
            'requirements' => $this->requirements ?? [],
            'target_audience' => $this->target_audience ?? [],
            'has_certificate' => $this->has_certificate,
            'max_students' => $this->max_students,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'og_image' => $this->og_image,
            'is_indexable' => $this->is_indexable ?? true,
        ];
    }
}
