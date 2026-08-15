<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'channel',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All stored preferences for a user keyed as "{category}.{channel}" => bool.
     *
     * @return array<string, bool>
     */
    public static function mapForUser(User $user): array
    {
        return static::query()
            ->where('user_id', $user->id)
            ->get(['category', 'channel', 'enabled'])
            ->mapWithKeys(fn (self $preference): array => [
                $preference->category.'.'.$preference->channel => (bool) $preference->enabled,
            ])
            ->all();
    }

    /**
     * The full (category × channel) matrix for the preferences screen, merging the
     * user's stored rows over the config defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matrixForUser(User $user): array
    {
        $stored = static::mapForUser($user);

        return array_map(function (NotificationCategory $category) use ($stored): array {
            $defaults = array_map(
                fn (NotificationChannel $channel): string => $channel->value,
                $category->defaultChannels()
            );

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'channels' => array_map(fn (NotificationChannel $channel): array => [
                    'channel' => $channel->value,
                    'enabled' => $stored[$category->value.'.'.$channel->value]
                        ?? in_array($channel->value, $defaults, true),
                    'locked' => ! $channel->isOptional(),
                ], NotificationChannel::cases()),
            ];
        }, NotificationCategory::cases());
    }

    /**
     * Replace a user's preferences with the given matrix.
     *
     * @param  array<string, array<string, bool>>  $matrix  category => channel => enabled
     */
    public static function syncForUser(User $user, array $matrix): void
    {
        foreach ($matrix as $category => $channels) {
            if (! NotificationCategory::tryFrom((string) $category)) {
                continue;
            }

            foreach ($channels as $channel => $enabled) {
                $channelEnum = NotificationChannel::tryFrom((string) $channel);

                if (! $channelEnum || ! $channelEnum->isOptional()) {
                    continue;
                }

                static::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'category' => $category,
                        'channel' => $channel,
                    ],
                    ['enabled' => (bool) $enabled],
                );
            }
        }
    }

    /**
     * @return Collection<int, self>
     */
    public static function forUser(User $user): Collection
    {
        return static::query()->where('user_id', $user->id)->get();
    }
}
