<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleStoreRequest;
use App\Http\Requests\Admin\RoleUpdateRequest;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware('admin', 'roles');
    }

    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->withCount('permissions', 'users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_locked' => $role->name === 'admin',
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'permissionGroups' => Permissions::groupedForPicker(
                null,
                Permission::query()->pluck('name'),
            ),
        ]);
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'تم إنشاء الدور بنجاح.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'is_locked' => $role->name === 'admin',
            ],
            'permissionGroups' => Permissions::groupedForPicker(
                null,
                Permission::query()->pluck('name'),
            ),
        ]);
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        if ($role->name !== 'admin') {
            $role->name = $data['name'];
            $role->save();
        }

        $role->syncPermissions($data['permissions'] ?? []);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'تم تحديث الدور بنجاح.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Protecting the admin role and roles still in use lives in RolePolicy now.
        $this->authorize('delete', $role);

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'تم حذف الدور بنجاح.');
    }
}
