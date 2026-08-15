<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Users are not "owned", so the rules here are safety rails rather than ownership.
 *
 * Two of them previously lived as inline `if` blocks in UserController; keeping them
 * in the policy means a future API route or console command cannot bypass them.
 * The "last admin" rule is new — without it the system can be locked out of itself.
 */
class UserPolicy extends BasePolicy
{
    protected string $resource = 'users';

    public function update(User $user, Model $model): bool|Response
    {
        if (! $user->can($this->permission('edit'))) {
            return false;
        }

        // Editing your own account is always fine (and is how profile edits work).
        if ($this->isSelf($user, $model)) {
            return true;
        }

        if ($this->targetIsAdmin($model) && ! $user->hasRole('admin')) {
            return Response::deny('لا تملك صلاحية تعديل حساب مدير.');
        }

        return true;
    }

    public function delete(User $user, Model $model): bool|Response
    {
        if (! $user->can($this->permission('delete'))) {
            return false;
        }

        if ($this->isSelf($user, $model)) {
            return Response::deny('لا يمكنك حذف حسابك الحالي.');
        }

        if ($this->targetIsAdmin($model)) {
            if (! $user->hasRole('admin')) {
                return Response::deny('لا تملك صلاحية حذف حساب مدير.');
            }

            if ($this->isLastAdmin($model)) {
                return Response::deny('لا يمكن حذف آخر حساب مدير في النظام.');
            }
        }

        return true;
    }

    protected function isSelf(User $user, Model $model): bool
    {
        return (int) $model->getKey() === (int) $user->id;
    }

    protected function targetIsAdmin(Model $model): bool
    {
        return $model instanceof User && $model->hasRole('admin');
    }

    /**
     * Deleting the final administrator would leave the panel unreachable.
     */
    protected function isLastAdmin(Model $model): bool
    {
        return User::query()->role('admin')->whereKeyNot($model->getKey())->doesntExist();
    }
}
