<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use App\Models\Concerns\HasPublicAvatar;
use App\Models\Concerns\HasStarRating;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's testimonial shown on the home page. Which ones appear, how many
 * and in what layout is decided by TestimonialSettings.
 *
 * Decimal casts are strings to static analysis without this.
 *
 * @property string $rating
 */
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use AuditsChanges, ExposesPolicyFlags, HasFactory, HasPublicAvatar, HasStarRating;

    /** Logical disk for uploaded avatars. */
    public const IMAGE_DISK = 'public';

    public const DEFAULT_COLOR = '#02ad8f';

    public const DEFAULT_ICON = 'fas fa-user';

    protected $fillable = [
        'name',
        'role',
        'company',
        'content',
        'rating',
        'avatar',
        'icon',
        'color',
        'course_id',
        'is_featured',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Testimonial>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The testimonials the home page shows for the given section settings.
     *
     * @param  Builder<Testimonial>  $query
     * @param  array{limit: int, order: string, featured_only: bool}  $settings
     */
    public function scopeForHomePage(Builder $query, array $settings): void
    {
        $query->active()
            // Only a course the public can open is linked from the card.
            ->with(['course' => fn ($course) => $course->published()->select('id', 'title', 'slug')])
            ->when($settings['featured_only'], fn (Builder $q) => $q->where('is_featured', true))
            ->when($settings['limit'] > 0, fn (Builder $q) => $q->take($settings['limit']));

        match ($settings['order']) {
            'latest' => $query->latest()->latest('id'),
            'rating' => $query->orderByDesc('rating')->orderBy('sort_order'),
            'random' => $query->inRandomOrder(),
            default => $query->orderBy('sort_order')->orderBy('id'),
        };
    }

    /**
     * "Role · Company", whichever of the two are set.
     */
    public function subtitle(): ?string
    {
        $parts = array_filter([$this->role, $this->company], 'filled');

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function accentColor(): string
    {
        return $this->color ?: self::DEFAULT_COLOR;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'can' => $this->policyFlags(),
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'company' => $this->company,
            'content' => $this->content,
            'rating' => (float) $this->rating,
            'avatar_url' => $this->avatarUrl(),
            'icon' => $this->icon,
            'color' => $this->color,
            'course_id' => $this->course_id,
            'course' => $this->course?->title,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'updated_at' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
