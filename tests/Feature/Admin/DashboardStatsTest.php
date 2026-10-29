<?php

namespace Tests\Feature\Admin;

use App\Models\SmtpSetting;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        Permission::findOrCreate('admin.dashboard.view', 'web');
        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    protected function stats(): DashboardStats
    {
        return app(DashboardStats::class);
    }

    protected function activeSmtp(): void
    {
        SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'u', 'password' => 'p', 'encryption' => 'tls',
            'from_address' => 'a@b.c', 'from_name' => 'T', 'is_default' => true, 'is_active' => true,
        ]);
    }

    protected function widget(array $widgets, string $key): array
    {
        return collect($widgets)->firstWhere('key', $key);
    }

    // ------------------------------------------------------------------ tiles

    public function test_the_user_tile_reports_the_real_count(): void
    {
        User::factory()->count(4)->create();

        $this->assertSame(4, $this->widget($this->stats()->overview(), 'users')['value']);
    }

    public function test_the_login_tile_counts_only_this_months_logins(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Password123!']);

        $this->assertSame(1, $this->widget($this->stats()->overview(), 'logins')['value']);
    }

    public function test_the_login_tile_ignores_failed_attempts(): void
    {
        User::factory()->create(['email' => 'a@b.test', 'password' => 'Password123!']);

        $this->post(route('login'), ['email' => 'a@b.test', 'password' => 'wrong']);

        $this->assertSame(0, $this->widget($this->stats()->overview(), 'logins')['value']);
    }

    public function test_the_messages_tile_counts_only_inbound_messages(): void
    {
        $contact = WhatsAppContact::findOrCreateByWaId('966500000000');

        foreach ([WhatsAppMessage::DIRECTION_INBOUND, WhatsAppMessage::DIRECTION_OUTBOUND] as $direction) {
            WhatsAppMessage::query()->create([
                'direction' => $direction,
                'contact_id' => $contact->id,
                'type' => WhatsAppMessage::TYPE_TEXT,
                'body' => 'x',
                'status' => WhatsAppMessage::STATUS_DELIVERED,
            ]);
        }

        $this->assertSame(1, $this->widget($this->stats()->overview(), 'messages')['value']);
    }

    public function test_no_tile_returns_a_hard_coded_placeholder(): void
    {
        // The dashboard previously shipped 128 users / 3,420 visits / 24 messages.
        $values = collect($this->stats()->overview())->pluck('value')->all();

        $this->assertNotContains(128, $values);
        $this->assertNotContains(3420, $values);
        $this->assertSame(0, $values[0], 'A fresh install must report zero users, not a placeholder.');
    }

    // ------------------------------------------------------------------ health

    public function test_health_is_one_hundred_when_every_check_passes(): void
    {
        $this->activeSmtp();

        $health = $this->stats()->systemHealth();

        $this->assertSame(100, $health['score']);
        $this->assertSame('يعمل بشكل طبيعي', $health['summary']);
    }

    public function test_health_drops_and_names_the_problem_when_a_job_fails(): void
    {
        $this->activeSmtp();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
        ]);

        $health = $this->stats()->systemHealth();

        $this->assertLessThan(100, $health['score']);
        $this->assertSame('توجد مهام فاشلة', $health['summary']);
    }

    public function test_health_counts_additional_problems_in_the_summary(): void
    {
        // No active SMTP AND a failed job.
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
        ]);

        $this->assertStringContainsString('(+1)', $this->stats()->systemHealth()['summary']);
    }

    public function test_whatsapp_check_passes_when_no_instances_are_configured(): void
    {
        $this->activeSmtp();

        $checks = collect($this->stats()->systemHealth()['checks']);

        // A project not using WhatsApp must not be marked unhealthy for it.
        $this->assertTrue($checks->firstWhere('label', 'أرقام واتساب متصلة')['passed']);
    }

    // ------------------------------------------------------------------ chart

    public function test_the_activity_chart_always_returns_a_full_window(): void
    {
        $chart = $this->stats()->activityChart(14);

        $this->assertCount(14, $chart);
        $this->assertSame(today()->subDays(13)->format('Y-m-d'), $chart[0]['date']);
        $this->assertSame(today()->format('Y-m-d'), $chart[13]['date']);
    }

    public function test_the_activity_chart_counts_real_activity(): void
    {
        $this->activeSmtp();   // creates one `created` activity row today

        $chart = collect($this->stats()->activityChart(14));

        $this->assertGreaterThan(0, $chart->firstWhere('date', today()->format('Y-m-d'))['total']);
    }

    // ------------------------------------------------------------------ page

    public function test_the_dashboard_page_receives_real_props(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->has('widgets', 4)
                ->has('lastLoginAt')
                // Trimmed on purpose: the page is the tiles and the shortcuts only.
                ->missing('todaySummary')
                ->missing('activityChart')
                ->missing('health')
                ->missing('recentActivity')
            );
    }

    public function test_the_dashboard_requires_its_permission(): void
    {
        $this->actingAs($this->panelUserWithoutPermissions())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_today_summary_reflects_todays_registrations(): void
    {
        User::factory()->count(3)->create();

        $summary = collect($this->stats()->todaySummary(User::first()));

        $this->assertSame(3, $summary->firstWhere('label', 'تسجيلات اليوم')['value']);
    }
}
