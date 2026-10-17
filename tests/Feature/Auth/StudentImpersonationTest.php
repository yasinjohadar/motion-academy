<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\StudentImpersonation;
use App\Services\Observability\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('admin.users.impersonate', 'web');
    }

    private function linkFor(User $admin, User $student): string
    {
        return $this->actingAs($admin)
            ->postJson(route('admin.users.impersonate', $student))
            ->assertOk()
            ->json('url');
    }

    private function securityEvents(string $event)
    {
        return Activity::query()->where('log_name', SecurityLogger::LOG_NAME)->where('event', $event)->get();
    }

    /**
     * Opening the link happens in another browser: no admin session there.
     */
    private function asFreshBrowser(): void
    {
        auth()->logout();
        $this->flushSession();
    }

    public function test_an_admin_gets_a_signed_link_for_a_student(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.users.impersonate', $student))
            ->assertOk()
            ->assertJsonPath('expires_in_minutes', StudentImpersonation::TTL_MINUTES)
            ->assertJsonPath('student.id', $student->id);

        $this->assertStringContainsString('/impersonate/', $response->json('url'));
        $this->assertStringContainsString('signature=', $response->json('url'));
        $this->assertCount(1, $this->securityEvents('impersonation_link_created'));
    }

    public function test_the_link_opens_the_student_account_in_another_browser(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'مدير', 'name_ar' => 'مدير']);
        $student = User::factory()->student()->create(['name' => 'سارة', 'name_ar' => 'سارة']);
        $url = $this->linkFor($admin, $student);

        $this->asFreshBrowser();

        $this->get($url)->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('أنت داخل حساب الطالب')
            ->assertSee('إنهاء الجلسة');

        // Logged as an admin action — and not as the student signing in.
        $this->assertCount(1, $this->securityEvents('impersonation_started'));
        $this->assertCount(0, $this->securityEvents('login'));
        $this->assertNull($student->fresh()->last_login_at);
    }

    public function test_the_link_works_only_once(): void
    {
        $url = $this->linkFor(User::factory()->admin()->create(), User::factory()->student()->create());

        $this->asFreshBrowser();
        $this->get($url)->assertRedirect(route('student.dashboard'));

        $this->asFreshBrowser();
        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_an_expired_or_tampered_link_is_refused(): void
    {
        $url = $this->linkFor(User::factory()->admin()->create(), User::factory()->student()->create());
        $this->asFreshBrowser();

        $this->get(str_replace('/impersonate/', '/impersonate/x', $url))->assertForbidden();

        $this->travel(StudentImpersonation::TTL_MINUTES + 1)->minutes();
        $this->get($url)->assertForbidden();
        $this->assertGuest();
    }

    public function test_admin_accounts_cannot_be_opened(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('admin.users.impersonate', $other))
            ->assertUnprocessable();
    }

    public function test_a_student_promoted_to_admin_after_the_link_was_made_is_not_opened(): void
    {
        $student = User::factory()->student()->create();
        $url = $this->linkFor(User::factory()->admin()->create(), $student);
        $student->syncRoles(['admin']);

        $this->asFreshBrowser();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_permission_is_required(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($this->panelUserWithoutPermissions())
            ->postJson(route('admin.users.impersonate', $student))
            ->assertForbidden();

        // Students never reach the panel at all.
        $this->actingAs(User::factory()->student()->create())
            ->postJson(route('admin.users.impersonate', $student))
            ->assertForbidden();
    }

    public function test_the_password_cannot_be_changed_from_an_admin_session(): void
    {
        $student = User::factory()->student()->create();
        $url = $this->linkFor(User::factory()->admin()->create(), $student);
        $this->asFreshBrowser();
        $this->get($url);

        $this->from(route('student.profile'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Changed-pass-123',
                'password_confirmation' => 'Changed-pass-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(password_verify('password', $student->fresh()->password));
    }

    public function test_ending_the_session_signs_the_browser_out(): void
    {
        $url = $this->linkFor(User::factory()->admin()->create(), User::factory()->student()->create());
        $this->asFreshBrowser();
        $this->get($url);

        $this->post(route('impersonate.leave'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_the_users_list_offers_the_button_for_students_only(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->student()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn ($page) => $page->where('users.data', fn ($rows) => collect($rows)->pluck('account_type')->sort()->values()->all() === ['admin', 'student']));
    }
}
