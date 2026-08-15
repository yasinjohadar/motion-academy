<?php

namespace App\Listeners\Security;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Audits role and permission changes.
 *
 * This cannot be done with the LogsActivity trait: syncRoles()/givePermissionTo()
 * write to pivot tables and never fire `updated` on the model. These four spatie
 * events (enabled via `permission.events_enabled`) are the only hook available, and
 * they cover the single most security-relevant change in the application.
 *
 * Recorded under log_name=security so it lands on the same timeline as logins.
 */
class LogAccessChanges
{
    public function __construct(private SecurityLogger $security) {}

    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->log($event->model, 'roles_attached', 'إضافة أدوار', 'roles', $event->rolesOrIds);
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->log($event->model, 'roles_detached', 'إزالة أدوار', 'roles', $event->rolesOrIds);
    }

    public function handlePermissionAttached(PermissionAttachedEvent $event): void
    {
        $this->log($event->model, 'permissions_attached', 'إضافة صلاحيات', 'permissions', $event->permissionsOrIds);
    }

    public function handlePermissionDetached(PermissionDetachedEvent $event): void
    {
        $this->log($event->model, 'permissions_detached', 'إزالة صلاحيات', 'permissions', $event->permissionsOrIds);
    }

    protected function log(Model $model, string $event, string $description, string $key, mixed $items): void
    {
        $names = $this->names($items, $key === 'roles'
            ? config('permission.models.role')
            : config('permission.models.permission'));

        $this->security->record(
            event: $event,
            description: $description,
            user: $model instanceof User ? $model : null,
            properties: array_filter([
                $key => $names,
                'subject_type' => $model::class,
                'subject_id' => $model->getKey(),
                // The signed-in administrator who made the change, which is usually a
                // different person from the account being changed.
                'changed_by' => auth()->user()?->email,
            ], fn ($value): bool => $value !== null && $value !== []),
        );
    }

    /**
     * Resolve whatever the event handed over into readable names.
     *
     * By the time spatie fires these events it has already reduced the argument to an
     * array of primary keys (see HasRoles::assignRole), so ids must be looked up —
     * logging raw ids would make the audit trail unreadable. Models, names and mixed
     * collections are all still handled, since the shape is not contractual.
     *
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    protected function names(mixed $items, string $modelClass): array
    {
        $items = $items instanceof Collection ? $items->all() : (is_array($items) ? $items : [$items]);

        $names = [];
        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof Model) {
                $names[] = (string) ($item->name ?? $item->getKey());
            } elseif (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $ids[] = $item;
            } elseif (is_string($item)) {
                $names[] = $item;
            }
        }

        if ($ids !== []) {
            $names = [...$names, ...$modelClass::query()->whereKey($ids)->pluck('name')->all()];
        }

        return array_values(array_unique(array_filter($names)));
    }
}
