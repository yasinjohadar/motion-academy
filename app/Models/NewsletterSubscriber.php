<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface|null $subscribed_at
 * @property CarbonInterface|null $unsubscribed_at
 */
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /** Labels for the places a sign-up can come from. */
    public const SOURCES = [
        'footer' => 'تذييل الموقع',
        'home' => 'الصفحة الرئيسية',
        'blog-detail' => 'صفحة مقال',
        'admin' => 'إضافة يدوية',
        'website' => 'الموقع',
    ];

    protected $fillable = [
        'email', 'name', 'status', 'source', 'token', 'ip_address', 'subscribed_at', 'unsubscribed_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscriber) {
            $subscriber->email = Str::lower(trim($subscriber->email));
            $subscriber->token = $subscriber->token ?: Str::random(48);
            $subscriber->status = $subscriber->status ?: self::STATUS_SUBSCRIBED;
            if ($subscriber->status === self::STATUS_SUBSCRIBED) {
                $subscriber->subscribed_at ??= now();
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSubscribed(Builder $query): void
    {
        $query->where('status', self::STATUS_SUBSCRIBED);
    }

    public function isSubscribed(): bool
    {
        return $this->status === self::STATUS_SUBSCRIBED;
    }

    public function subscribe(?string $source = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUBSCRIBED,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
            'source' => $source ?? $this->source,
        ])->save();
    }

    public function unsubscribe(): void
    {
        $this->forceFill([
            'status' => self::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ])->save();
    }

    public function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }

    public static function sourceLabel(?string $source): string
    {
        return self::SOURCES[$source] ?? (string) $source;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'is_subscribed' => $this->isSubscribed(),
            'source' => $this->source,
            'source_label' => self::sourceLabel($this->source),
            'subscribed_at' => $this->subscribed_at?->format('Y-m-d H:i'),
            'unsubscribed_at' => $this->unsubscribed_at?->format('Y-m-d H:i'),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
