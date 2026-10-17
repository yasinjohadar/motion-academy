<?php

namespace App\Support;

use App\Models\User;

/**
 * Every account is one of two kinds, decided by its roles alone:
 *
 *  - student: the `student` role (every public sign-up) — learns on the public
 *             site (/dashboard, /learn). An account with no role at all counts as
 *             a student too, so a role-less account never lands in the panel.
 *  - admin:   any other role — `admin`, or a role built on the Roles screen — works
 *             in the control panel (/admin). What it may do there is then decided
 *             by that role's permissions.
 */
class AccountType
{
    public const ADMIN = 'admin';

    public const STUDENT = 'student';

    /** @var array<string, string> */
    public const LABELS = [
        self::ADMIN => 'أدمن',
        self::STUDENT => 'طالب',
    ];

    public static function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Load explicitly: this runs on users taken from lists, where lazy
        // loading is disabled (Model::shouldBeStrict).
        $user->loadMissing('roles');

        return $user->roles->pluck('name')->contains(fn (string $name) => $name !== self::STUDENT);
    }

    public static function of(User $user): string
    {
        return self::isAdmin($user) ? self::ADMIN : self::STUDENT;
    }

    /**
     * Make the account exactly this kind: one role, admin or student.
     */
    public static function assign(User $user, string $type): void
    {
        $user->syncRoles([$type === self::ADMIN ? self::ADMIN : self::STUDENT]);
    }
}
