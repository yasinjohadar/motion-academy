<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserStoreRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\User;
use App\Notifications\Users\UserCreated;
use App\Notifications\Users\UserRoleChanged;
use App\Services\Users\CredentialsMessage;
use App\Services\Users\UserProfile;
use App\Support\AccountType;
use App\Support\NotificationChannelResolver;
use App\Support\NotificationRecipients;
use App\Support\PhoneInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        // The details page is part of reading the list: same permission.
        return self::panelResourceMiddleware('admin', 'users', false, [], ['index', 'show']);
    }

    public function index(Request $request): Response
    {
        $query = trim((string) $request->string('query'));

        $users = User::query()
            // Roles decide the account type (AccountType::of).
            ->with('roles')
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
                'account_type' => AccountType::of($user),
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

    public function show(Request $request, User $user, UserProfile $profile, CredentialsMessage $credentials): Response
    {
        return Inertia::render('Admin/Users/Show', [
            ...$profile->build($user, $request->user()),
            'credentials' => AccountType::isAdmin($user) ? null : [
                'template' => $credentials->template(),
                'presets' => CredentialsMessage::PRESETS,
                'placeholders' => CredentialsMessage::PLACEHOLDERS,
                'login_url' => route('login'),
                'site' => $credentials->siteName(),
                'whatsapp' => app(NotificationChannelResolver::class)->availabilityFor($user)[NotificationChannel::WhatsApp->value]
                    ?? ['available' => false, 'reason' => 'قناة واتساب غير مثبّتة.'],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'accountTypes' => AccountType::LABELS,
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
            'phone_e164' => PhoneInput::canonicalFromAny($data['phone']),
            'password' => $data['password'],
        ]);

        // Not part of the create() array: `email_verified_at` is deliberately absent
        // from User's fillable list, so mass assignment silently dropped it and every
        // admin-created user stayed unverified — locked out by EnsureEmailIsVerified.
        // An account an admin created in person needs no email round-trip.
        $user->markEmailAsVerified();

        AccountType::assign($user, $data['account_type']);

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
                'account_type' => AccountType::of($user),
            ],
            'accountTypes' => AccountType::LABELS,
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
            'phone_e164' => PhoneInput::canonicalFromAny($data['phone'], $user->id),
        ]);

        if ($user->isDirty('phone_e164')) {
            $user->phone_verified_at = null;
        }

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $previousRoles = $user->getRoleNames()->values()->all();

        $user->save();
        AccountType::assign($user, $data['account_type']);

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
}
