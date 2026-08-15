<?php

namespace Tests\Feature\Observability;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function securityEvents(?string $event = null): Collection
    {
        return Activity::query()
            ->where('log_name', SecurityLogger::LOG_NAME)
            ->when($event, fn ($q) => $q->where('event', $event))
            ->get();
    }

    public function test_a_successful_login_is_recorded_with_ip_and_user_agent(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect();

        $activity = $this->securityEvents('login')->first();

        $this->assertNotNull($activity, 'No login event was recorded.');
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertArrayHasKey('ip', $activity->properties->all());
    }

    public function test_a_successful_login_updates_last_login_columns(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->assertNull($user->last_login_at);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!']);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    public function test_updating_last_login_does_not_create_an_audit_row(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        // The factory's own `created` row already exists, so compare the delta.
        $before = Activity::query()->where('log_name', 'users')->count();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!']);

        // saveQuietly keeps every login from adding a noisy `users` activity row.
        $this->assertSame($before, Activity::query()->where('log_name', 'users')->count());
        $this->assertSame(0, Activity::query()->where('log_name', 'users')->where('event', 'updated')->count());
    }

    public function test_a_failed_login_is_recorded_without_a_causer(): void
    {
        User::factory()->create(['email' => 'target@example.test', 'password' => 'Password123!']);

        $this->post(route('login'), [
            'email' => 'target@example.test',
            'password' => 'the-wrong-password',
        ]);

        $activity = $this->securityEvents('login_failed')->first();

        $this->assertNotNull($activity, 'No failed-login event was recorded.');
        $this->assertNull($activity->causer_id);
        $this->assertSame('target@example.test', $activity->properties['attempted_email']);
        $this->assertTrue($activity->properties['user_exists']);
    }

    public function test_a_failed_login_never_records_the_attempted_password(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        $this->post(route('login'), [
            'email' => 'target@example.test',
            'password' => 'MySecretAttempt999',
        ]);

        $raw = $this->securityEvents('login_failed')->pluck('properties')->toJson();

        $this->assertStringNotContainsString('MySecretAttempt999', $raw);
    }

    public function test_repeated_failed_logins_are_throttled_to_one_row(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        // Three attempts, same email and IP — the throttle must collapse them.
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login'), [
                'email' => 'target@example.test',
                'password' => "wrong-{$i}",
            ]);
        }

        $this->assertCount(1, $this->securityEvents('login_failed'));
    }

    public function test_failed_logins_for_different_emails_are_recorded_separately(): void
    {
        foreach (['one@example.test', 'two@example.test'] as $email) {
            $this->post(route('login'), ['email' => $email, 'password' => 'wrong']);
        }

        $this->assertCount(2, $this->securityEvents('login_failed'));
    }

    public function test_a_lockout_is_recorded(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        // LoginRequest allows 5 attempts before firing Lockout.
        for ($i = 0; $i < 7; $i++) {
            $this->post(route('login'), ['email' => 'target@example.test', 'password' => 'wrong']);
        }

        $this->assertCount(1, $this->securityEvents('lockout'));
    }

    public function test_a_logout_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $activity = $this->securityEvents('logout')->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
    }

    public function test_attaching_a_role_is_audited_on_the_security_timeline(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();

        $user->assignRole('admin');

        $activity = $this->securityEvents('roles_attached')->first();

        $this->assertNotNull($activity, 'Role attachment was not audited.');
        $this->assertContains('admin', $activity->properties['roles']);
        $this->assertSame($user->id, $activity->causer_id);
    }

    public function test_detaching_a_role_is_audited(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $user->removeRole('admin');

        $this->assertCount(1, $this->securityEvents('roles_detached'));
    }

    public function test_sync_roles_is_audited_even_though_it_fires_no_model_update(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('admin-staff', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin-staff');

        $user->syncRoles(['admin']);

        // This is the case the LogsActivity trait cannot see at all.
        $this->assertTrue($this->securityEvents('roles_attached')->isNotEmpty());
    }

    public function test_a_direct_permission_grant_is_audited(): void
    {
        Permission::findOrCreate('admin.users.list', 'web');
        $user = User::factory()->create();

        $user->givePermissionTo('admin.users.list');

        $activity = $this->securityEvents('permissions_attached')->first();

        $this->assertNotNull($activity);
        $this->assertContains('admin.users.list', $activity->properties['permissions']);
    }

    public function test_the_acting_administrator_is_recorded_on_a_role_change(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create(['email' => 'boss@example.test']);
        $target = User::factory()->create();

        $this->actingAs($admin);
        $target->assignRole('admin');

        $activity = $this->securityEvents('roles_attached')->first();

        $this->assertSame('boss@example.test', $activity->properties['changed_by']);
        $this->assertSame($target->id, $activity->properties['subject_id']);
    }
}
