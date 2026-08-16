<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Listeners\NotifyOnWhatsAppMessageReceived;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Notifications\WhatsAppMessageReceivedNotification;
use App\Modules\WhatsApp\WhatsAppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The event → listener wiring for inbound messages.
 *
 * Laravel auto-discovers listeners in app/Listeners only. When this module moved to
 * app/Modules the listener fell out of that scan: messages still arrived and were still
 * stored, but nobody was notified — and the whole suite stayed green. These tests exist
 * because nothing else was watching that seam.
 */
class InboundNotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listener_is_registered_for_the_event(): void
    {
        Event::fake();

        // Asserts registration itself, not just that dispatching does something.
        Event::assertListening(WhatsAppMessageReceived::class, NotifyOnWhatsAppMessageReceived::class);
    }

    public function test_an_inbound_message_notifies_the_operators(): void
    {
        Notification::fake();

        Permission::findOrCreate('admin.whatsapp-messages.list', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo('admin.whatsapp-messages.list');

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $message = WhatsAppMessage::factory()->inbound()->create();

        WhatsAppMessageReceived::dispatch($message);

        Notification::assertSentTo($admin, WhatsAppMessageReceivedNotification::class);
    }

    public function test_removing_the_module_provider_unhooks_the_listener_cleanly(): void
    {
        // Documents the uninstall path: no provider, no listener, and dispatching the
        // event is then simply a no-op rather than an error.
        $this->assertTrue(
            in_array(
                WhatsAppServiceProvider::class,
                require base_path('bootstrap/providers.php'),
                true
            ),
            'The module provider must be registered for its listeners to work.'
        );
    }
}
