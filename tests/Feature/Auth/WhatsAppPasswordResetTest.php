<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Support\CapturingOtpChannel;
use Tests\TestCase;

class WhatsAppPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CapturingOtpChannel::install();
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'student@example.com',
            'phone' => '+963 991 234 567',
            'phone_e164' => '963991234567',
        ]);
    }

    public function test_forgot_password_page_offers_whatsapp_only_when_available(): void
    {
        $this->get(route('password.request'))
            ->assertInertia(fn ($page) => $page
                ->where('whatsappAvailable', true)
                ->where('defaultCountry', '963')
                ->has('countries'));

        app(OtpSettings::class)->update(['reset_enabled' => false]);

        $this->get(route('password.request'))
            ->assertInertia(fn ($page) => $page->where('whatsappAvailable', false));

        $this->post(route('password.whatsapp.send'), ['country_code' => '963', 'phone' => '991234567'])
            ->assertRedirect(route('login'));
        $this->assertSame([], CapturingOtpChannel::$delivered);
    }

    public function test_a_correct_code_leads_to_the_reset_form_with_a_valid_token(): void
    {
        $user = $this->member();

        $this->post(route('password.whatsapp.send'), ['country_code' => '963', 'phone' => '0991234567'])
            ->assertRedirect(route('password.whatsapp.verify'));

        $this->assertSame(OtpPurpose::PasswordReset, CapturingOtpChannel::$delivered[0]['purpose']);

        $this->get(route('password.whatsapp.verify'))
            ->assertInertia(fn ($page) => $page
                ->component('Auth/OtpVerify')
                ->where('purpose', OtpPurpose::PasswordReset->value));

        $response = $this->post(route('password.whatsapp.check'), ['code' => CapturingOtpChannel::lastCode()]);

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(url('reset-password').'/', (string) $location);
        $this->assertStringContainsString('email=student%40example.com', (string) $location);

        $token = basename((string) parse_url((string) $location, PHP_URL_PATH));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));

        // The code verifies the phone, it does not sign anyone in.
        $this->assertGuest();
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_completing_the_reset_changes_the_password(): void
    {
        $user = $this->member();

        $this->post(route('password.whatsapp.send'), ['country_code' => '963', 'phone' => '991234567']);
        $location = (string) $this->post(route('password.whatsapp.check'), ['code' => CapturingOtpChannel::lastCode()])
            ->headers->get('Location');
        $token = basename((string) parse_url($location, PHP_URL_PATH));

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
    }

    public function test_an_unknown_number_gets_the_same_answer_but_no_code(): void
    {
        $this->member();

        $this->post(route('password.whatsapp.send'), ['country_code' => '963', 'phone' => '944000111'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('password.whatsapp.verify'));

        $this->assertSame([], CapturingOtpChannel::$delivered);

        $this->post(route('password.whatsapp.check'), ['code' => '123456'])->assertSessionHasErrors('code');
    }

    public function test_a_login_code_cannot_be_used_to_reset_the_password(): void
    {
        $this->member();

        $this->post(route('login.otp.send'), ['country_code' => '963', 'phone' => '991234567']);
        $loginCode = CapturingOtpChannel::lastCode();

        $this->post(route('password.whatsapp.send'), ['country_code' => '963', 'phone' => '991234567']);
        $resetCode = CapturingOtpChannel::lastCode();

        if ($loginCode === $resetCode) {
            $this->markTestSkipped('Both random codes happened to match.');
        }

        $this->post(route('password.whatsapp.check'), ['code' => $loginCode])->assertSessionHasErrors('code');
    }

    public function test_verify_screen_without_a_pending_code_goes_back(): void
    {
        $this->get(route('password.whatsapp.verify'))->assertRedirect(route('password.request'));
    }
}
