<?php

namespace App\Services\Observability;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Active sessions, read straight from the framework's `sessions` table.
 *
 * SESSION_DRIVER=database is already in use, and that table is the only place in the
 * whole schema carrying an ip_address — so this screen needs no new schema and no new
 * writes at all.
 */
class SessionInspector
{
    public function available(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(): LengthAwarePaginator
    {
        $table = config('session.table', 'sessions');

        $paginator = DB::table($table)
            ->orderByDesc('last_activity')
            ->paginate((int) config('observability.per_page', 25));

        $users = User::query()
            ->whereIn('id', collect($paginator->items())->pluck('user_id')->filter()->unique())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $currentId = session()->getId();

        return $paginator->through(fn (object $row): array => [
            'id' => $row->id,
            'is_current' => $row->id === $currentId,
            'user' => $row->user_id && $users->has($row->user_id) ? [
                'id' => $users[$row->user_id]->id,
                'name' => $users[$row->user_id]->name,
                'email' => $users[$row->user_id]->email,
            ] : null,
            'ip_address' => $row->ip_address,
            'user_agent' => Str::limit((string) $row->user_agent, 70),
            'device' => $this->device((string) $row->user_agent),
            'last_activity' => $row->last_activity
                ? now()->setTimestamp((int) $row->last_activity)->format('Y-m-d H:i:s')
                : null,
            'last_activity_human' => $row->last_activity
                ? now()->setTimestamp((int) $row->last_activity)->diffForHumans()
                : null,
        ]);
    }

    /**
     * Revoke a single session. Returns false when the id does not exist.
     */
    public function revoke(string $sessionId): bool
    {
        return DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete() > 0;
    }

    /**
     * Revoke every session belonging to a user, optionally keeping the current one.
     */
    public function revokeForUser(int $userId, ?string $exceptSessionId = null): int
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    public function activeCount(): int
    {
        if (! $this->available()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))->count();
    }

    /**
     * A coarse device label — enough to recognise your own session in the list
     * without pulling in a user-agent parsing dependency.
     */
    protected function device(string $userAgent): string
    {
        return match (true) {
            $userAgent === '' => 'غير معروف',
            Str::contains($userAgent, ['iPhone', 'iPad', 'iPod']) => 'iOS',
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, 'Windows') => 'Windows',
            Str::contains($userAgent, ['Macintosh', 'Mac OS']) => 'macOS',
            Str::contains($userAgent, ['Linux', 'X11']) => 'Linux',
            default => 'أخرى',
        };
    }
}
