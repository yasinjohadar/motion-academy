<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every account is an admin (control panel) or a student (public dashboard).
 */
class AccountTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.dashboard.view', 'admin.users.list', 'admin.users.create', 'admin.users.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        Role::findOrCreate('student', 'web');
    }

    /**
     * @return array<string, string>
     */
    private function userPayload(User $user, string $type): array
    {
        return [
            'name_ar' => $user->name_ar ?? 'مستخدم',
            'name_en' => $user->name_en ?? 'User',
            'email' => $user->email,
            'phone' => $user->phone,
            'account_type' => $type,
        ];
    }

    public function test_a_new_sign_up_is_a_student_and_lands_on_the_student_dashboard(): void
    {
        $this->post(route('register'), [
            'name' => 'طالب',
            'email' => 'new@example.com',
            'country_code' => '963',
            'phone' => '991234567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('student.dashboard', absolute: false));

        $user = User::query()->where('email', 'new@example.com')->sole();
        $this->assertSame(['student'], $user->getRoleNames()->all());
        $this->assertSame(AccountType::STUDENT, AccountType::of($user));
    }

    public function test_login_sends_admins_to_the_panel_and_students_to_their_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));

        auth()->logout();

        $this->post(route('login'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_only_the_student_role_goes_to_the_site_every_other_role_opens_the_panel(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole(Role::findOrCreate('content-editor', 'web'));
        $student = User::factory()->student()->create();
        $roleless = User::factory()->create();

        $this->assertTrue(AccountType::isAdmin($editor));
        $this->assertFalse(AccountType::isAdmin($student));
        // No role at all is treated as a student, so it can never land in the panel.
        $this->assertFalse(AccountType::isAdmin($roleless));

        // A custom role without the dashboard permission lands on a panel page it can open.
        $this->post(route('login'), ['email' => $editor->email, 'password' => 'password'])
            ->assertRedirect(route('admin.profile.edit', absolute: false));
        $this->get(route('admin.profile.edit'))->assertOk();
        auth()->logout();

        $this->post(route('login'), ['email' => $roleless->email, 'password' => 'password'])
            ->assertRedirect(route('student.dashboard', absolute: false));
        $this->get(route('admin.profile.edit'))->assertRedirect(route('student.dashboard'));
    }

    public function test_a_student_given_admin_permissions_directly_still_stays_on_the_site(): void
    {
        $student = User::factory()->student()->create();
        $student->givePermissionTo(Permission::findOrCreate('admin.dashboard.view', 'web'));

        $this->assertFalse(AccountType::isAdmin($student));
        $this->actingAs($student)->get(route('admin.dashboard'))->assertRedirect(route('student.dashboard'));
    }

    public function test_students_cannot_open_the_control_panel(): void
    {
        $student = User::factory()->student()->create();

        foreach (['admin.dashboard', 'admin.profile.edit', 'admin.notifications.index', 'admin.users.index'] as $route) {
            $this->actingAs($student)->get(route($route))->assertRedirect(route('student.dashboard'));
        }

        $this->actingAs($student)->getJson(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admins_open_the_control_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_admin_creates_accounts_as_admin_or_student(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertInertia(fn ($page) => $page->where('accountTypes', AccountType::LABELS));

        foreach (['student' => 'student@example.com', 'admin' => 'second-admin@example.com'] as $type => $email) {
            $this->actingAs($admin)->post(route('admin.users.store'), [
                'name_ar' => 'حساب',
                'name_en' => 'Account',
                'email' => $email,
                'phone' => '+963 944 '.random_int(100000, 999999),
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'account_type' => $type,
            ])->assertSessionHasNoErrors();

            $this->assertSame([$type], User::query()->where('email', $email)->sole()->getRoleNames()->all());
        }

        $this->actingAs($admin)->post(route('admin.users.store'), ['account_type' => 'teacher'])
            ->assertSessionHasErrors('account_type');
    }

    public function test_users_list_shows_each_account_type(): void
    {
        // Several rows: strict mode only rejects lazy loading on models loaded in a list.
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $custom = $this->panelUserWithoutPermissions();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('users.data', fn ($rows) => collect($rows)->pluck('account_type', 'id')->all() === [
                $custom->id => 'admin',
                $student->id => 'student',
                $admin->id => 'admin',
            ]));
    }

    public function test_changing_the_account_type_replaces_the_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->student()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $user))
            ->assertInertia(fn ($page) => $page->where('user.account_type', 'student'));

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->userPayload($user, 'admin'))
            ->assertSessionHasNoErrors();
        $this->assertSame(['admin'], $user->fresh()->getRoleNames()->all());

        $this->actingAs($admin)->put(route('admin.users.update', $user), $this->userPayload($user, 'student'))
            ->assertSessionHasNoErrors();
        $this->assertSame(['student'], $user->fresh()->getRoleNames()->all());
    }

    public function test_an_admin_cannot_demote_themselves_or_the_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), $this->userPayload($admin, 'student'))
            ->assertSessionHasErrors('account_type');
        $this->assertTrue($admin->fresh()->hasRole('admin'));

        // With a second admin, demoting the other one is allowed…
        $other = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.users.update', $other), $this->userPayload($other, 'student'))
            ->assertSessionHasNoErrors();

        // …and a panel account with only the users permissions cannot demote the last admin.
        $manager = $this->panelUserWithoutPermissions();
        $manager->givePermissionTo(['admin.users.list', 'admin.users.edit']);
        $this->actingAs($manager)->put(route('admin.users.update', $admin), $this->userPayload($admin, 'student'))
            ->assertSessionHasErrors('account_type');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_the_site_menu_offers_the_panel_to_admins_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('home'))->assertSee('لوحة الإدارة');

        auth()->logout();

        $this->actingAs(User::factory()->student()->create())->get(route('home'))->assertDontSee('لوحة الإدارة');
    }
}
