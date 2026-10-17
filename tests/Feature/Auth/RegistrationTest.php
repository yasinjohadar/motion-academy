<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\PanelRedirector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'country_code' => '963',
            'phone' => '0991 234 567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('defaultCountry', '963')
                ->has('countries'));
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $user = User::where('email', 'test@example.com')->first();
        $response->assertRedirect(PanelRedirector::url($user));

        $this->assertSame('963991234567', $user->phone_e164);
        $this->assertSame('+963 991 234 567', $user->phone);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_phone_is_required_and_must_be_valid(): void
    {
        $this->post('/register', $this->payload(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->payload(['phone' => '123']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->payload(['country_code' => 'abc']))->assertSessionHasErrors('country_code');

        $this->assertGuest();
    }

    public function test_a_phone_already_on_another_account_is_rejected(): void
    {
        User::factory()->create(['phone_e164' => '963991234567']);

        // Same number written differently is still the same number.
        $this->post('/register', $this->payload(['phone' => '+963991234567']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }
}
