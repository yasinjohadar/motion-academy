<?php

namespace Tests\Feature\Policies;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Safety rules that used to live as inline `if` blocks inside the controllers.
 * Moving them into policies means a console command or future API route cannot
 * skip them.
 */
class SafetyRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function userWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // ------------------------------------------------------------------ users

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $user = $this->userWith(['admin.users.delete']);

        $response = Gate::forUser($user)->inspect('delete', $user);

        $this->assertTrue($response->denied());
        $this->assertSame('لا يمكنك حذف حسابك الحالي.', $response->message());
    }

    public function test_the_last_admin_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        // Two admins: deleting one is fine.
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $other));

        $other->delete();

        // One admin left: deleting them would lock everyone out of the panel.
        $survivor = User::query()->role('admin')->first();
        $deleter = $this->admin();          // a third admin doing the deleting
        $deleter->removeRole('admin');
        $deleter->givePermissionTo(Permission::findOrCreate('admin.users.delete', 'web'));

        $response = Gate::forUser($deleter)->inspect('delete', $survivor);

        $this->assertTrue($response->denied());
    }

    public function test_a_non_admin_cannot_delete_an_admin_account(): void
    {
        $target = $this->admin();
        $actor = $this->userWith(['admin.users.delete']);

        $response = Gate::forUser($actor)->inspect('delete', $target);

        $this->assertTrue($response->denied());
        $this->assertSame('لا تملك صلاحية حذف حساب مدير.', $response->message());
    }

    public function test_a_non_admin_cannot_edit_an_admin_account(): void
    {
        $target = $this->admin();
        $actor = $this->userWith(['admin.users.edit']);

        $response = Gate::forUser($actor)->inspect('update', $target);

        $this->assertTrue($response->denied());
        $this->assertSame('لا تملك صلاحية تعديل حساب مدير.', $response->message());
    }

    public function test_a_user_may_always_edit_their_own_account(): void
    {
        $user = $this->userWith(['admin.users.edit']);

        $this->assertTrue(Gate::forUser($user)->allows('update', $user));
    }

    public function test_editing_an_ordinary_user_is_allowed(): void
    {
        $actor = $this->userWith(['admin.users.edit']);
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($actor)->allows('update', $target));
    }

    // ------------------------------------------------------------------ roles

    public function test_the_admin_role_cannot_be_deleted(): void
    {
        $actor = $this->userWith(['admin.roles.delete']);
        $role = Role::findOrCreate('admin', 'web');

        $response = Gate::forUser($actor)->inspect('delete', $role);

        $this->assertTrue($response->denied());
        $this->assertSame('لا يمكن حذف دور المدير الأعلى.', $response->message());
    }

    public function test_a_role_still_assigned_to_users_cannot_be_deleted(): void
    {
        $actor = $this->userWith(['admin.roles.delete']);
        $role = Role::findOrCreate('editor', 'web');

        User::factory()->create()->assignRole('editor');

        $response = Gate::forUser($actor)->inspect('delete', $role);

        $this->assertTrue($response->denied());
        $this->assertSame('لا يمكن حذف دور مرتبط بمستخدمين.', $response->message());
    }

    public function test_an_unused_role_can_be_deleted(): void
    {
        $actor = $this->userWith(['admin.roles.delete']);
        $role = Role::findOrCreate('unused', 'web');

        $this->assertTrue(Gate::forUser($actor)->allows('delete', $role));
    }

    // ------------------------------------------------------------------ admin bypass

    public function test_an_admin_bypasses_ordinary_policies(): void
    {
        $admin = $this->admin();

        $post = BlogPost::query()->create([
            'title' => 'مقال', 'slug' => 'admin-bypass', 'content' => 'نص',
            'status' => 'draft', 'author_id' => User::factory()->create()->id,
        ]);

        // Gate::before short-circuits ordinary policies for admins — worth knowing,
        // because it makes the policy layer look inert when tested as an admin.
        $this->assertTrue(Gate::forUser($admin)->allows('update', $post));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $post));
    }

    public function test_an_admin_still_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        // Gate::before deliberately defers on User/Role subjects. Without that
        // exception an admin could delete themselves and lock the panel.
        $response = Gate::forUser($admin)->inspect('delete', $admin);

        $this->assertTrue($response->denied());
        $this->assertSame('لا يمكنك حذف حسابك الحالي.', $response->message());
    }

    public function test_an_admin_still_cannot_delete_the_last_admin(): void
    {
        $lastAdmin = $this->admin();
        $actor = $this->admin();

        // Remove the actor's admin role so only one admin remains, then have them
        // try to delete that survivor.
        $actor->removeRole('admin');
        $actor->givePermissionTo(Permission::findOrCreate('admin.users.delete', 'web'));

        $this->assertTrue(Gate::forUser($actor)->inspect('delete', $lastAdmin)->denied());
    }

    public function test_an_admin_can_still_delete_an_ordinary_user(): void
    {
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->allows('delete', User::factory()->create()));
    }

    public function test_an_admin_can_still_delete_an_unused_role(): void
    {
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->allows('delete', Role::findOrCreate('spare', 'web')));
    }
}
