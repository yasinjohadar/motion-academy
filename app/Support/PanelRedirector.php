<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class PanelRedirector
{
    /**
     * Resolve the post-login home route name for a user.
     */
    public static function routeName(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user) {
            return 'login';
        }

        if ($user->hasRole('admin') || $user->can('admin.dashboard.view')) {
            return self::panelHome('admin') ?? 'admin.dashboard';
        }

        if (self::userHasPanelAccess($user, 'admin')) {
            return 'admin.profile.edit';
        }

        if ($user->hasRole('teacher') || self::userHasPanelAccess($user, 'teacher')) {
            $teacherHome = self::panelHome('teacher');
            if ($teacherHome && Route::has($teacherHome)) {
                return $teacherHome;
            }
        }

        return 'admin.profile.edit';
    }

    public static function url(?User $user = null): string
    {
        return route(self::routeName($user), absolute: false);
    }

    protected static function panelHome(string $panel): ?string
    {
        $home = config("permissions.panels.{$panel}.home_route");

        return is_string($home) && $home !== '' ? $home : null;
    }

    protected static function userHasPanelAccess(User $user, string $panel): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->getAllPermissions()
            ->contains(fn ($permission) => str_starts_with($permission->name, "{$panel}."));
    }
}
