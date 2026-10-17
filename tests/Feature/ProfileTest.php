<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this
            ->actingAs($user)
            ->get('/admin/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/admin/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_page_splits_the_phone_into_country_and_number(): void
    {
        $user = User::factory()->admin()->create(['phone_e164' => '963991234567', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertInertia(fn ($page) => $page
                ->where('phone.country_code', '963')
                ->where('phone.phone', '991234567')
                ->where('phone.verified', true)
                ->has('countries'));
    }

    public function test_changing_the_phone_saves_it_and_clears_its_verification(): void
    {
        $user = User::factory()->admin()->create(['phone_e164' => '963991234567', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/admin/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'country_code' => '963',
                'phone' => '0944 111 222',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('963944111222', $user->phone_e164);
        $this->assertSame('+963 944 111 222', $user->phone);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_keeping_the_same_phone_keeps_its_verification(): void
    {
        $user = User::factory()->admin()->create(['phone_e164' => '963991234567', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->patch('/admin/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'country_code' => '963',
                'phone' => '991234567',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_taken_by_another_account_is_rejected(): void
    {
        User::factory()->admin()->create(['phone_e164' => '963944111222']);
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->patch('/admin/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'country_code' => '963',
                'phone' => '944111222',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/admin/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/admin/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this
            ->actingAs($user)
            ->from('/admin/profile')
            ->delete('/admin/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/admin/profile');

        $this->assertNotNull($user->fresh());
    }
}
