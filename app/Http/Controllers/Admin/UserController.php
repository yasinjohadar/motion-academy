<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Notifications\Users\UserCreated;
use App\Notifications\Users\UserRoleChanged;
use App\Support\NotificationRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware('admin', 'users');
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $users = User::query()
            ->with('roles:id,name')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name_ar', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name_ar' => $user->name_ar,
                'name_en' => $user->name_en,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => optional($user->created_at)?->format('Y-m-d'),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'query' => $query,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name_ar'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($data['roles'] ?? []);

        Notification::send(
            NotificationRecipients::forPermission('admin.users.list'),
            new UserCreated($user)
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name_ar' => $user->name_ar,
                'name_en' => $user->name_en,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames()->values(),
            ],
            'roles' => $this->availableRoles(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        $user->fill([
            'name' => $data['name_ar'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $previousRoles = $user->getRoleNames()->values()->all();

        $user->save();
        $user->syncRoles($data['roles'] ?? []);

        $currentRoles = $user->refresh()->getRoleNames()->values()->all();

        if ($previousRoles !== $currentRoles) {
            Notification::send(
                NotificationRecipients::forPermissionIncluding('admin.users.edit', $user),
                new UserRoleChanged($user, $previousRoles, $currentRoles)
            );
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    public function destroy(User $user): RedirectResponse
    {
        // Self-deletion, admin protection and the last-admin rule all live in
        // UserPolicy now, so a future API route cannot skip them.
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'تم حذف المستخدم بنجاح.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    protected function availableRoles(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ->values()
            ->all();
    }
}
