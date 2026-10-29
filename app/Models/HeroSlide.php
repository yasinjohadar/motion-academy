<?php

namespace App\Models;

use App\Enums\HeroSlideVisual;
use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use App\Services\Storage\AppStorageManager;
use Carbon\CarbonInterface;
use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * One slide of the home page hero. Everything the slide shows is editable from
 * the admin panel; the slider's own behaviour (autoplay, effect, how many
 * slides) lives in HeroSliderSettings.
 *
 * Enum and datetime casts are invisible to static analysis without these.
 *
 * @property HeroSlideVisual $visual
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use AuditsChanges, ExposesPolicyFlags, HasFactory;

    /** Logical disk for uploaded slide images. */
    public const IMAGE_DISK = 'public';

    protected $fillable = [
        'badge_text',
        'badge_icon',
        'title',
        'title_highlight',
        'description',
        'primary_button_label',
        'primary_button_url',
        'primary_button_icon',
        'secondary_button_label',
        'secondary_button_url',
        'secondary_button_icon',
        'bullet_label',
        'visual',
        'image',
        'image_alt',
        'show_stats',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'visual' => HeroSlideVisual::class,
            'show_stats' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Active slides whose schedule window contains now, in display order.
     *
     * @param  Builder<HeroSlide>  $query
     */
    public function scopeShowing(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The title as HTML: escaped, with the highlight phrase wrapped in the gradient
     * span and line breaks kept.
     */
    public function titleHtml(): HtmlString
    {
        $title = e($this->title);
        $highlight = filled($this->title_highlight) ? e($this->title_highlight) : null;

        if ($highlight !== null && Str::contains($title, $highlight)) {
            $title = Str::replaceFirst($highlight, '<span class="ma-gradient-text">'.$highlight.'</span>', $title);
        }

        return new HtmlString(nl2br($title, false));
    }

    public function hasUploadedImage(): bool
    {
        return AppStorageManager::isStoredPath($this->image);
    }

    public function imageUrl(): ?string
    {
        return app(AppStorageManager::class)->publicUrl(self::IMAGE_DISK, $this->image);
    }

    /**
     * Whether the slide is on the home page right now: active and inside its window.
     */
    public function isShowing(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'can' => $this->policyFlags(),
            'id' => $this->id,
            'badge_text' => $this->badge_text,
            'badge_icon' => $this->badge_icon,
            'title' => $this->title,
            'title_highlight' => $this->title_highlight,
            'description' => $this->description,
            'primary_button_label' => $this->primary_button_label,
            'primary_button_url' => $this->primary_button_url,
            'primary_button_icon' => $this->primary_button_icon,
            'secondary_button_label' => $this->secondary_button_label,
            'secondary_button_url' => $this->secondary_button_url,
            'secondary_button_icon' => $this->secondary_button_icon,
            'bullet_label' => $this->bullet_label,
            'visual' => $this->visual->value,
            'visual_label' => $this->visual->label(),
            'image_url' => $this->imageUrl(),
            'image_alt' => $this->image_alt,
            'show_stats' => $this->show_stats,
            'is_active' => $this->is_active,
            'is_showing' => $this->isShowing(),
            'sort_order' => $this->sort_order,
            'starts_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'creator' => $this->creator?->name,
            'updated_at' => optional($this->updated_at)?->format('Y-m-d H:i'),
        ];
    }
}
