<?php

namespace App\Notifications\Channels;

use App\Notifications\BaseNotification;
use App\Providers\AppServiceProvider;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Extends Laravel's database channel to also persist the indexed `category`,
 * `level` and `action_url` columns, so the notification centre can filter and
 * count without JSON queries.
 *
 * Bound over the framework channel in {@see AppServiceProvider}.
 */
class AppDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if (! $notification instanceof BaseNotification) {
            return $payload;
        }

        return [
            ...$payload,
            'category' => $notification->category()->value,
            'level' => $notification->level()->value,
            'action_url' => $notification->actionUrl($notifiable),
        ];
    }
}
