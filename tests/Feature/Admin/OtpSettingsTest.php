<?php

namespace Tests\Feature\Admin;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\CapturingOtpChannel;
use Tests\TestCase;

class OtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CapturingOtpChannel::install();

        foreach (['list', 'edit'] as $action) {
            Permission::findOrCreate("admin.otp-settings.{$action}", 'web');
        }
        Permission::findOrCreate('admin.dashboard.view', 'web');

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'login_enabled' => true,
            'reset_enabled' => true,
            'message_template' => 'رمزك: {code} صالح {minutes} دقائق',
            'ttl_minutes' => 5,
            'max_attempts' => 3,
            'resend_cooldown_seconds' => 90,
        ], $overrides);
    }

    public function test_page_shows_settings_and_readiness(): void
    {
        User::factory()->create(['phone' => null, 'phone_e164' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.otp-settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Otp')
                ->where('settings.login_enabled', true)
                ->where('settings.reset_enabled', true)
                ->where('readiness.channel', true)
                ->where('readiness.users_total', 2)
                ->where('readiness.users_with_phone', 1)
                ->has('defaultTemplate')
                ->has('countries'));
    }

    public function test_admin_can_save_settings_and_they_take_effect(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.otp-settings.update'), $this->validSettings(['login_enabled' => false]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $settings = app(OtpSettings::class);
        $this->assertFalse($settings->isAvailable(OtpPurpose::Login));
        $this->assertTrue($settings->isAvailable(OtpPurpose::PasswordReset));
        $this->assertSame(5, $settings->ttlMinutes());
        $this->assertSame(3, $settings->maxAttempts());
        $this->assertSame(90, $settings->resendCooldown());
        $this->assertSame('رمزك: 111222 صالح 5 دقائق', OtpPurpose::Login->message('111222', 5));
    }

    public function test_template_must_contain_the_code_placeholder_and_limits_are_enforced(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.otp-settings.update'), $this->validSettings([
                'message_template' => 'رسالة بلا رمز',
                'ttl_minutes' => 99,
                'max_attempts' => 0,
                'resend_cooldown_seconds' => 5,
            ]))
            ->assertSessionHasErrors(['message_template', 'ttl_minutes', 'max_attempts', 'resend_cooldown_seconds']);
    }

    public function test_test_send_delivers_a_code_to_the_given_number(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.otp-settings.test'), ['country_code' => '963', 'phone' => '0991234567'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertCount(1, CapturingOtpChannel::$delivered);
        $this->assertSame('963991234567', CapturingOtpChannel::$delivered[0]['identifier']);
    }

    public function test_test_send_rejects_bad_numbers_and_reports_a_missing_channel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.otp-settings.test'), ['country_code' => '963', 'phone' => '12'])
            ->assertSessionHasErrors('phone');

        config()->set('otp.channel', null);

        $this->actingAs($admin)
            ->post(route('admin.otp-settings.test'), ['country_code' => '963', 'phone' => '991234567'])
            ->assertSessionHas('error');

        $this->assertSame([], CapturingOtpChannel::$delivered);
    }

    public function test_users_without_permission_cannot_view_or_change_settings(): void
    {
        $outsider = $this->panelUserWithoutPermissions();

        $this->actingAs($outsider)->get(route('admin.otp-settings.edit'))->assertForbidden();
        $this->actingAs($outsider)->put(route('admin.otp-settings.update'), $this->validSettings(['login_enabled' => false]));
        $this->actingAs($outsider)->post(route('admin.otp-settings.test'), ['country_code' => '963', 'phone' => '991234567']);

        $this->assertTrue(app(OtpSettings::class)->isAvailable(OtpPurpose::Login));
        $this->assertSame([], CapturingOtpChannel::$delivered);
    }

    public function test_viewer_with_list_permission_cannot_edit(): void
    {
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('admin.otp-settings.list', 'admin.dashboard.view');

        $this->actingAs($viewer)->get(route('admin.otp-settings.edit'))->assertOk();
        $this->actingAs($viewer)->put(route('admin.otp-settings.update'), $this->validSettings(['login_enabled' => false]));

        $this->assertTrue(app(OtpSettings::class)->isAvailable(OtpPurpose::Login));
    }
}
