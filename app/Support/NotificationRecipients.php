<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who should receive a notification.
 *
 * Keeps the "everyone who can see backups" style logic in one place instead of
 * repeating a spatie query at every trigger site.
 */
class NotificationRecipients
{
    /**
     * Users holding the given permission, plus every admin (admins bypass
     * permission checks everywhere else in this app — see resources/js/hooks/useCan.js).
     *
     * @return Collection<int, User>
     */
    public static function forPermission(string $permission): Collection
    {
        try {
            $byPermission = User::query()->permission($permission)->get();
        } catch (\Throwable) {
            // Permission not registered yet (fresh install / mid-migration).
            $byPermission = collect();
        }

        return $byPermission
            ->merge(static::admins())
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function admins(): Collection
    {
        try {
            return User::query()->role('admin')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * The recipients for `$permission` plus one specific user (typically whoever
     * triggered the action), de-duplicated.
     *
     * @return Collection<int, User>
     */
    public static function forPermissionIncluding(string $permission, ?User $user): Collection
    {
        $recipients = static::forPermission($permission);

        if ($user) {
            $recipients = $recipients->merge([$user])->unique('id')->values();
        }

        return $recipients;
    }
}
