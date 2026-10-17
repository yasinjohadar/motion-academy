<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Where an account lands after signing in: admins in the control panel,
 * students in their dashboard on the public site (see AccountType).
 */
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

        if (AccountType::isAdmin($user)) {
            // A custom admin role without the dashboard still has a page of its own.
            return $user->hasRole(AccountType::ADMIN) || $user->can('admin.dashboard.view')
                ? (self::panelHome('admin') ?? 'admin.dashboard')
                : 'admin.profile.edit';
        }

        $studentHome = self::panelHome('student');

        return $studentHome && Route::has($studentHome) ? $studentHome : 'student.dashboard';
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
}
