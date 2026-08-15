<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Default authorization for every panel resource.
 *
 * This layer sits *beside* the existing permission middleware, not instead of it:
 * the middleware answers "may you reach this screen at all?", the policy answers
 * "may you touch this particular record?".
 *
 * Why every listed model needs a policy, even a five-line one: Gate::allows() returns
 * FALSE when no policy is registered. Once list rows carry `can` flags, a model
 * without a policy would silently lose its edit and delete buttons — looking like a
 * bug rather than a decision. Subclassing this with just a `$resource` restores the
 * plain permission behaviour.
 *
 * Note that Gate::before in PermissionServiceProvider grants admins everything, so
 * these rules only ever govern non-admin roles.
 */
abstract class BasePolicy
{
    protected string $panel = 'admin';

    /** Resource key as it appears in config/permissions.php (e.g. "blog-posts"). */
    protected string $resource;

    /** Column holding the creator's id. Null means the resource has no owner. */
    protected ?string $ownerColumn = null;

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('list'));
    }

    public function view(User $user, Model $model): bool
    {
        // Listing implies reading a row; a dedicated `show` permission is only used
        // where the resource actually defines one.
        return $user->can($this->permission('show')) || $user->can($this->permission('list'));
    }

    public function create(User $user): bool
    {
        return $user->can($this->permission('create'));
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $this->authorizeOwned(
            $user,
            $model,
            $this->permission('edit'),
            $this->permission('edit-any'),
            'لا تملك صلاحية تعديل عنصر أنشأه مستخدم آخر.'
        );
    }

    public function delete(User $user, Model $model): bool|Response
    {
        return $this->authorizeOwned(
            $user,
            $model,
            $this->permission('delete'),
            $this->permission('delete-any'),
            'لا تملك صلاحية حذف عنصر أنشأه مستخدم آخر.'
        );
    }

    public function restore(User $user, Model $model): bool|Response
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool|Response
    {
        return $this->delete($user, $model);
    }

    /**
     * The shared owner rule:
     *   no base permission        → denied outright
     *   resource has no owner     → base permission is enough
     *   user owns the record      → allowed
     *   user holds the -any grant → allowed
     *   otherwise                 → denied with a readable reason
     */
    protected function authorizeOwned(
        User $user,
        Model $model,
        string $basePermission,
        string $anyPermission,
        string $denialMessage,
    ): bool|Response {
        if (! $user->can($basePermission)) {
            return false;
        }

        if ($this->ownerColumn === null) {
            return true;
        }

        if ($this->owns($user, $model)) {
            return true;
        }

        if ($user->can($anyPermission)) {
            return true;
        }

        return Response::deny($denialMessage);
    }

    protected function owns(User $user, Model $model): bool
    {
        if ($this->ownerColumn === null) {
            return false;
        }

        $ownerId = $model->getAttribute($this->ownerColumn);

        // An unowned record (null creator) is not "owned" by anyone — it takes the
        // -any grant to modify, which is the safe reading for legacy rows.
        return $ownerId !== null && (int) $ownerId === (int) $user->id;
    }

    /**
     * Builds "{panel}.{resource}.{action}", reusing the central registry so policy
     * names can never drift from config/permissions.php.
     */
    protected function permission(string $action): string
    {
        $crud = Permissions::crud($this->panel, $this->resource);

        return match ($action) {
            'list' => $crud['list'],
            'create' => $crud['create'],
            'edit' => $crud['edit'],
            'delete' => $crud['delete'],
            'show' => $crud['show'],
            'edit-any' => $crud['editAny'],
            'delete-any' => $crud['deleteAny'],
            default => "{$this->panel}.{$this->resource}.{$action}",
        };
    }

    public function resourceKey(): string
    {
        return $this->resource;
    }

    public function isOwnable(): bool
    {
        return $this->ownerColumn !== null;
    }
}
