<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Contracts\Role as RoleContract;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
                return null;
            }

            /*
             * Admins bypass every policy — except those protecting the integrity of
             * the access system itself. Without this exception an admin can delete
             * their own account, or the last remaining admin, and lock everyone out
             * of the panel permanently.
             *
             * Returning null defers to UserPolicy / RolePolicy, which still grant
             * admins everything else (their `can()` checks short-circuit here because
             * a bare ability string carries no subject).
             */
            $subject = $arguments[0] ?? null;

            if ($subject instanceof User || $subject instanceof RoleContract) {
                return null;
            }

            return true;
        });

        // Spatie's Role lives outside App\Models, so Laravel's convention-based
        // policy discovery cannot find App\Policies\RolePolicy on its own.
        Gate::policy(config('permission.models.role'), RolePolicy::class);
    }
}
