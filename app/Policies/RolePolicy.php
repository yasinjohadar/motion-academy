<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Rules moved out of RoleController::destroy.
 *
 * The subject here is Spatie\Permission\Models\Role, which lives outside App\Models,
 * so Laravel's policy auto-discovery cannot find this class by convention — it is
 * registered explicitly in PermissionServiceProvider.
 */
class RolePolicy extends BasePolicy
{
    protected string $resource = 'roles';

    public function update(User $user, Model $model): bool|Response
    {
        if (! $user->can($this->permission('edit'))) {
            return false;
        }

        if ($this->isAdminRole($model) && ! $user->hasRole('admin')) {
            return Response::deny('لا تملك صلاحية تعديل دور المدير الأعلى.');
        }

        return true;
    }

    public function delete(User $user, Model $model): bool|Response
    {
        if (! $user->can($this->permission('delete'))) {
            return false;
        }

        if ($this->isAdminRole($model)) {
            return Response::deny('لا يمكن حذف دور المدير الأعلى.');
        }

        if ($model->users()->exists()) {
            return Response::deny('لا يمكن حذف دور مرتبط بمستخدمين.');
        }

        return true;
    }

    protected function isAdminRole(Model $model): bool
    {
        return $model->getAttribute('name') === 'admin';
    }
}
