<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpSettings;
use App\Support\PanelRedirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingOtpChannel;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CapturingOtpChannel::install();
    }

    private function member(): User
    {
        return User::factory()->create(['phone' => '+963 991 234 567', 'phone_e164' => '963991234567']);
    }

    public function test_login_page_offers_the_whatsapp_option_only_when_available(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('otpLoginAvailable', true));

        app(OtpSettings::class)->update(['login_enabled' => false]);

        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('otpLoginAvailable', false));
    }

    public function test_feature_is_hidden_when_no_delivery_channel_is_configured(): void
    {
        config()->set('otp.channel', null);

        $this->get(route('login'))
            ->assertInertia(fn ($page) => $page->where('otpLoginAvailable', false));

        $this->get(route('login.otp'))->assertRedirect(route('login'));
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567'])
            ->assertRedirect(route('login'));
    }

    public function test_disabled_feature_redirects_every_step_to_the_login_page(): void
    {
        app(OtpSettings::class)->update(['login_enabled' => false]);

        $this->get(route('login.otp'))->assertRedirect(route('login'));
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567'])
            ->assertRedirect(route('login'));

        $this->assertSame([], CapturingOtpChannel::$delivered);
    }

    public function test_phone_entry_screen_renders_with_the_country_list(): void
    {
        $this->get(route('login.otp'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/OtpLogin')
                ->where('defaultCountry', '963')
                ->has('countries'));
    }

    public function test_a_registered_number_receives_a_code_and_can_sign_in_with_it(): void
    {
        $user = $this->member();

        // Typed the Syrian way: with the national trunk zero.
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '0991 234 567'])
            ->assertRedirect(route('login.otp.verify'));

        $this->assertCount(1, CapturingOtpChannel::$delivered);
        $this->assertSame('963991234567', CapturingOtpChannel::$delivered[0]['identifier']);
        $this->assertSame(OtpPurpose::Login, CapturingOtpChannel::$delivered[0]['purpose']);

        $this->get(route('login.otp.verify'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/OtpVerify')
                ->where('maskedPhone', '+963 ••• ••• 567')
                ->where('purpose', OtpPurpose::Login->value));

        $this->post(route('login.otp.check'), ['code' => CapturingOtpChannel::lastCode()])
            ->assertRedirect(PanelRedirector::url($user));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_an_unknown_number_gets_the_same_answer_but_no_code(): void
    {
        $this->member();

        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '944000111'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login.otp.verify'));

        $this->assertSame([], CapturingOtpChannel::$delivered);

        $this->get(route('login.otp.verify'))->assertOk();

        $this->post(route('login.otp.check'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_wrong_code_does_not_sign_in(): void
    {
        $this->member();
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567']);

        $wrong = CapturingOtpChannel::lastCode() === '000000' ? '111111' : '000000';

        $this->post(route('login.otp.check'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->post(route('login.otp.check'), ['code' => 'abc'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $user = $this->member();
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567']);
        $code = CapturingOtpChannel::lastCode();

        $this->post(route('login.otp.check'), ['code' => $code]);
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->post(route('login.otp.check'), ['code' => $code])->assertRedirect(route('login.otp'));
        $this->assertGuest();
    }

    public function test_verify_screen_without_a_pending_code_goes_back_to_phone_entry(): void
    {
        $this->get(route('login.otp.verify'))->assertRedirect(route('login.otp'));
        $this->post(route('login.otp.check'), ['code' => '123456'])->assertRedirect(route('login.otp'));
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '12'])
            ->assertSessionHasErrors('phone');
        $this->post(route('login.otp.send'), ['country_code' => 'xx', 'phone' => '991234567'])
            ->assertSessionHasErrors('country_code');
    }

    public function test_resend_respects_the_cooldown(): void
    {
        $this->member();
        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567']);

        $this->from(route('login.otp.verify'))
            ->post(route('login.otp.resend'))
            ->assertSessionHasErrors('code');
        $this->assertCount(1, CapturingOtpChannel::$delivered);

        $this->travel(61)->seconds();

        $this->from(route('login.otp.verify'))
            ->post(route('login.otp.resend'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertCount(2, CapturingOtpChannel::$delivered);

        // Only the newest code works.
        $this->post(route('login.otp.check'), ['code' => CapturingOtpChannel::$delivered[1]['code']]);
        $this->assertAuthenticated();
    }

    public function test_requesting_too_many_codes_is_throttled_with_a_readable_message(): void
    {
        config()->set('otp.max_per_window', 2);
        $this->member();

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567'])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567'])
            ->assertSessionHasErrors('phone');

        $this->assertCount(2, CapturingOtpChannel::$delivered);
    }

    public function test_the_custom_message_template_is_used(): void
    {
        app(OtpSettings::class)->update(['message_template' => 'كودك {code} لمدة {minutes}']);

        $this->assertSame('كودك 123456 لمدة 10', OtpPurpose::Login->message('123456', 10));
    }
}
