<?php

namespace Tests\Feature\Observability;

use App\Models\SmtpSetting;
use App\Models\User;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObservabilityScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(config('permissions.panels.admin.groups.observability.permissions')) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function admin(): User
    {
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    protected function failedJob(array $overrides = []): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\CreateBackupJob', 'attempts' => 1]),
            'exception' => "RuntimeException: disk full\n#0 /app/foo.php(1)",
            'failed_at' => now(),
            ...$overrides,
        ]);

        return $uuid;
    }

    // ---------------------------------------------------------------- access

    public function test_guests_are_redirected_from_every_screen(): void
    {
        foreach ([
            'admin.observability.index',
            'admin.observability.activity',
            'admin.observability.security',
            'admin.observability.sessions',
            'admin.observability.failed-jobs',
            'admin.observability.webhooks',
        ] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    public function test_a_user_without_any_observability_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.observability.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.observability.activity'))->assertForbidden();
    }

    public function test_the_activity_permission_does_not_grant_the_security_screen(): void
    {
        $user = $this->userWith(['admin.observability.activity']);

        $this->actingAs($user)->get(route('admin.observability.activity'))->assertOk();
        $this->actingAs($user)->get(route('admin.observability.security'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.observability.failed-jobs'))->assertForbidden();
    }

    public function test_the_jobs_permission_does_not_grant_the_audit_screen(): void
    {
        $user = $this->userWith(['admin.observability.jobs']);

        $this->actingAs($user)->get(route('admin.observability.failed-jobs'))->assertOk();
        $this->actingAs($user)->get(route('admin.observability.activity'))->assertForbidden();
    }

    public function test_the_hub_is_reachable_with_any_single_observability_permission(): void
    {
        $this->actingAs($this->userWith(['admin.observability.logs']))
            ->get(route('admin.observability.index'))
            ->assertOk();
    }

    public function test_the_log_viewer_gate_requires_its_permission(): void
    {
        $without = User::factory()->create();
        $with = $this->userWith(['admin.observability.logs']);

        $this->assertFalse($without->can('admin.observability.logs'));
        $this->assertTrue(Gate::forUser($with)->allows('viewLogViewer'));
        $this->assertFalse(Gate::forUser($without)->allows('viewLogViewer'));
    }

    // ---------------------------------------------------------------- screens

    public function test_the_hub_reports_the_counters(): void
    {
        $this->failedJob();

        $this->actingAs($this->admin())
            ->get(route('admin.observability.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Observability/Index')
                ->where('stats.failed_jobs', 1)
                ->has('stats.audit_today')
                ->has('queue.worker_command')
            );
    }

    public function test_the_worker_command_names_every_queue_in_use(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.observability.index'))
            ->assertInertia(function ($page) {
                $command = $page->toArray()['props']['queue']['worker_command'];

                // A bare `queue:work` silently ignores broadcast/mail/whatsapp.
                foreach (['broadcast', 'default', 'mail', 'whatsapp'] as $queue) {
                    $this->assertStringContainsString($queue, $command);
                }
            });
    }

    public function test_the_audit_screen_lists_changes_and_excludes_security_events(): void
    {
        $admin = $this->admin();

        // A model change (audit) and a login (security).
        SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'u', 'password' => 'p', 'encryption' => 'tls',
            'from_address' => 'a@b.c', 'from_name' => 'T', 'is_default' => true, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.observability.activity'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = $page->toArray()['props']['items']['data'];

                $this->assertNotEmpty($rows);
                foreach ($rows as $row) {
                    $this->assertNotSame('security', $row['log_name']);
                }
            });
    }

    public function test_the_security_screen_shows_only_security_events(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!']);

        $this->actingAs($this->admin())
            ->get(route('admin.observability.security'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = $page->toArray()['props']['items']['data'];

                $this->assertNotEmpty($rows);
                foreach ($rows as $row) {
                    $this->assertSame('security', $row['log_name']);
                }
            });
    }

    public function test_the_detail_screen_shows_the_old_and_new_values(): void
    {
        $setting = SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.old.com', 'port' => 587,
            'username' => 'u', 'password' => 'p', 'encryption' => 'tls',
            'from_address' => 'a@b.c', 'from_name' => 'T', 'is_default' => true, 'is_active' => true,
        ]);
        $setting->update(['host' => 'smtp.new.com']);

        $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

        $this->actingAs($this->admin())
            ->get(route('admin.observability.show', $activity->id))
            ->assertOk()
            ->assertInertia(function ($page) {
                $changes = collect($page->toArray()['props']['item']['changes']);
                $host = $changes->firstWhere('field', 'host');

                $this->assertSame('smtp.old.com', $host['old']);
                $this->assertSame('smtp.new.com', $host['new']);
            });
    }

    public function test_the_sessions_screen_degrades_when_sessions_are_not_in_the_database(): void
    {
        // The test suite runs with SESSION_DRIVER=array, which is exactly the case
        // the screen has to explain rather than crash on.
        config()->set('session.driver', 'array');

        $this->actingAs($this->admin())
            ->get(route('admin.observability.sessions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Observability/Sessions')
                ->where('driver', 'array')
                ->where('items', null)
            );
    }

    public function test_the_sessions_screen_reads_the_sessions_table_when_available(): void
    {
        config()->set('session.driver', 'database');

        DB::table('sessions')->insert([
            'id' => 'test-session-id',
            'user_id' => null,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/151',
            'payload' => base64_encode('x'),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.observability.sessions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('driver', 'database')
                ->has('items.data', 1)
                ->where('items.data.0.ip_address', '203.0.113.7')
                ->where('items.data.0.device', 'Windows')
            );
    }

    // ---------------------------------------------------------------- failed jobs

    public function test_the_failed_jobs_screen_lists_and_summarises_failures(): void
    {
        $this->failedJob();

        $this->actingAs($this->admin())
            ->get(route('admin.observability.failed-jobs'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.job_label', 'CreateBackupJob')
                ->where('items.data.0.queue', 'default')
                ->where('items.data.0.exception_summary', 'RuntimeException: disk full')
            );
    }

    public function test_a_failed_job_can_be_deleted(): void
    {
        $uuid = $this->failedJob();

        $this->actingAs($this->admin())
            ->from(route('admin.observability.failed-jobs'))
            ->delete(route('admin.observability.failed-jobs.forget', $uuid))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_deleting_an_unknown_failed_job_reports_an_error(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.observability.failed-jobs'))
            ->delete(route('admin.observability.failed-jobs.forget', (string) Str::uuid()))
            ->assertSessionHas('error');
    }

    public function test_all_failed_jobs_can_be_flushed(): void
    {
        $this->failedJob();
        $this->failedJob();

        $this->actingAs($this->admin())
            ->delete(route('admin.observability.failed-jobs.flush'))
            ->assertRedirect(route('admin.observability.failed-jobs'));

        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_failed_jobs_can_be_filtered_by_queue(): void
    {
        $this->failedJob();
        $this->failedJob(['queue' => 'mail']);

        $this->actingAs($this->admin())
            ->get(route('admin.observability.failed-jobs', ['queue' => 'mail']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    // ---------------------------------------------------------------- webhooks

    public function test_the_webhook_screen_lists_events_and_derives_the_type(): void
    {
        WhatsAppWebhookEvent::query()->create([
            'event_id' => 'evt-1',
            'payload' => ['event' => 'messages.upsert', 'instance' => 'primary'],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.observability.webhooks'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                // event_type is derived by the model's saving hook.
                ->where('items.data.0.event_type', 'messages.upsert')
                ->where('items.data.0.instance', 'primary')
                ->where('items.data.0.is_processed', false)
            );
    }

    public function test_webhook_events_can_be_filtered_by_state(): void
    {
        WhatsAppWebhookEvent::query()->create(['event_id' => 'a', 'payload' => ['event' => 'x']]);
        WhatsAppWebhookEvent::query()->create([
            'event_id' => 'b', 'payload' => ['event' => 'y'], 'processed_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.observability.webhooks', ['state' => 'unprocessed']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        $this->actingAs($this->admin())
            ->get(route('admin.observability.webhooks', ['state' => 'processed']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }
}
