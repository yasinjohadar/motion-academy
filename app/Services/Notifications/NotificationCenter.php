<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationCenter
{
    /**
     * The payload shared with every Inertia response for the header bell.
     * Two indexed queries — kept deliberately cheap.
     *
     * @return array{unread_count: int, items: array<int, array<string, mixed>>, realtime: array{enabled: bool, poll_interval: int}}
     */
    public function snapshot(User $user): array
    {
        $limit = (int) config('notifications.recent_limit', 10);

        return [
            'unread_count' => $this->unreadCount($user),
            'items' => AppNotification::query()
                ->forUser($user)
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (AppNotification $notification): array => $notification->toAdminArray())
                ->all(),
            'realtime' => [
                'enabled' => (bool) config('notifications.realtime.enabled')
                    && config('broadcasting.default') !== 'null',
                'poll_interval' => (int) config('notifications.realtime.poll_interval', 30000),
            ],
        ];
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::query()->forUser($user)->whereNull('read_at')->count();
    }

    /**
     * @param  array{category?: ?string, level?: ?string, state?: ?string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        return AppNotification::query()
            ->forUser($user)
            ->category($filters['category'] ?? null)
            ->level($filters['level'] ?? null)
            ->readState($filters['state'] ?? null)
            ->latest('created_at')
            ->paginate((int) config('notifications.per_page', 20))
            ->withQueryString()
            ->through(fn (AppNotification $notification): array => $notification->toAdminArray());
    }

    /**
     * Scoped lookup — never resolve a notification without binding it to its owner.
     */
    public function findForUser(User $user, string $id): ?AppNotification
    {
        return AppNotification::query()->forUser($user)->whereKey($id)->first();
    }

    public function markAllRead(User $user): int
    {
        return AppNotification::query()
            ->forUser($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function deleteAll(User $user): int
    {
        return AppNotification::query()->forUser($user)->delete();
    }
}
