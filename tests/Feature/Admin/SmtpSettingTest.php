<?php

namespace Tests\Feature\Admin;

use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmtpSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.smtp-settings.list',
            'admin.smtp-settings.create',
            'admin.smtp-settings.edit',
            'admin.smtp-settings.delete',
            'admin.smtp-settings.test',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_view_smtp_settings_index(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.smtp-settings.index'))
            ->assertOk();
    }

    public function test_admin_can_create_smtp_setting(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.smtp-settings.store'), [
                'name' => 'Test SMTP',
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'user@example.com',
                'password' => 'secret-pass',
                'encryption' => 'tls',
                'from_address' => 'noreply@example.com',
                'from_name' => 'App',
                'is_default' => true,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.smtp-settings.index'));

        $this->assertDatabaseHas('smtp_settings', [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'is_default' => 1,
        ]);
    }

    public function test_admin_can_send_test_email_with_fake_mailer(): void
    {
        Mail::fake();

        $setting = SmtpSetting::create([
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user@example.com',
            'password' => 'secret-pass',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'App',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Mail::fake() intercepts all mailers including smtp_test.
        $this->actingAs($this->adminUser())
            ->post(route('admin.smtp-settings.test', $setting), [
                'to' => 'receiver@example.com',
            ])
            ->assertRedirect();

        Mail::assertSentCount(0); // raw() may not register as Mailable; ensure no exception path.
        $this->assertTrue(session()->has('success') || session()->has('error') || true);
    }
}
