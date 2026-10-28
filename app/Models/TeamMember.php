<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use App\Models\Concerns\HasPublicAvatar;
use App\Models\Concerns\HasStarRating;
use App\Support\SocialPlatforms;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of the expert team shown on the home page. Which members appear,
 * how many and in what layout is decided by TeamSettings.
 *
 * Decimal and JSON casts are invisible to static analysis without these.
 *
 * @property string|null $rating
 * @property array<int, array{platform: string, url: string}>|null $social_links
 */
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use AuditsChanges, ExposesPolicyFlags, HasFactory, HasPublicAvatar, HasStarRating;

    /** Logical disk for uploaded avatars. */
    public const IMAGE_DISK = 'public';

    public const DEFAULT_COLOR = '#02ad8f';

    public const DEFAULT_ICON = 'fas fa-user-tie';

    protected $fillable = [
        'name',
        'title',
        'bio',
        'avatar',
        'icon',
        'color',
        'rating',
        'profile_url',
        'social_links',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'social_links' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<TeamMember>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The members the home page shows for the given section settings.
     *
     * @param  Builder<TeamMember>  $query
     * @param  array{limit: int, order: string}  $settings
     */
    public function scopeForHomePage(Builder $query, array $settings): void
    {
        $query->active()
            ->when($settings['limit'] > 0, fn (Builder $q) => $q->take($settings['limit']));

        match ($settings['order']) {
            'rating' => $query->orderByRaw('rating IS NULL')->orderByDesc('rating')->orderBy('sort_order'),
            'random' => $query->inRandomOrder(),
            default => $query->orderBy('sort_order')->orderBy('id'),
        };
    }

    /**
     * Social links ready to render; email addresses become mailto: links.
     *
     * @return array<int, array{platform: string, label: string, icon: string, color: string, url: string}>
     */
    public function socialButtons(): array
    {
        return SocialPlatforms::buttons($this->social_links);
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
            'title' => $this->title,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl(),
            'icon' => $this->icon,
            'color' => $this->color,
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'profile_url' => $this->profile_url,
            'social_links' => array_values($this->social_links ?? []),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'updated_at' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
