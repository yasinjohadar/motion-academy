<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real-time notifications travel on `private-App.Models.User.{id}`. Only that
 * user may subscribe — otherwise anyone could read another person's alerts.
 *
 * Tests run on the `null` broadcaster, which approves every channel, so this
 * switches to Reverb (dummy credentials; signing needs no running server) and
 * registers routes/channels.php on it.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]);

        require base_path('routes/channels.php');
    }

    private function authorize(User $user, int $channelOwnerId)
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$channelOwnerId,
        ]);
    }

    public function test_a_user_may_listen_to_their_own_notifications(): void
    {
        $user = User::factory()->admin()->create();

        $this->authorize($user, $user->id)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_nobody_may_listen_to_someone_elses_notifications(): void
    {
        $owner = User::factory()->admin()->create();

        $this->authorize(User::factory()->student()->create(), $owner->id)->assertForbidden();
        $this->authorize(User::factory()->admin()->create(), $owner->id)->assertForbidden();
    }

    public function test_guests_cannot_subscribe(): void
    {
        $owner = User::factory()->create();

        $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$owner->id,
        ])->assertForbidden();
    }
}
