<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\WhatsApp\Jobs\BroadcastWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppEvolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.whatsapp-settings.list',
            'admin.whatsapp-settings.edit',
            'admin.whatsapp-instances.list',
            'admin.whatsapp-instances.create',
            'admin.whatsapp-instances.edit',
            'admin.whatsapp-instances.delete',
            'admin.whatsapp-send.create',
            'admin.whatsapp-campaigns.list',
            'admin.whatsapp-campaigns.create',
            'admin.whatsapp-campaigns.show',
            'admin.whatsapp-campaigns.edit',
            'admin.whatsapp-campaigns.delete',
            'admin.whatsapp-messages.list',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create(['phone' => '966500000001']);
        $user->assignRole('admin');

        return $user;
    }

    public function test_admin_can_view_whatsapp_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.whatsapp-settings.index'))
            ->assertOk();
    }

    public function test_admin_can_save_whatsapp_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), [
                'whatsapp_provider' => 'evolution',
                'evolution_base_url' => 'https://evolution.example.com',
                'evolution_api_key' => 'test-key',
                'evolution_instance_name' => 'main',
                'evolution_rotation_enabled' => true,
                'delay_between_messages' => 3,
                'delay_between_broadcasts' => 5,
                'max_messages_per_minute' => 20,
                'random_delay_enabled' => true,
                'min_delay' => 2,
                'max_delay' => 5,
            ])
            ->assertRedirect(route('admin.whatsapp-settings.index'));

        $settings = app(WhatsAppSettingsService::class)->getSettings();
        $this->assertSame('https://evolution.example.com', $settings['evolution_base_url']);
        $this->assertTrue($settings['evolution_rotation_enabled']);
    }

    public function test_admin_can_view_instances_page(): void
    {
        EvolutionInstance::create([
            'instance_name' => 'main',
            'connection_status' => 'open',
            'rotation_enabled' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.whatsapp-instances.index'))
            ->assertOk();
    }

    public function test_campaign_can_be_created_with_manual_phones(): void
    {
        Bus::fake([BroadcastWhatsAppMessageJob::class]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.whatsapp-campaigns.store'), [
                'message' => 'مرحباً {name}',
                'user_ids' => [],
                'manual_phones' => "966511111111\n966522222222",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'total_recipients' => 2,
        ]);
        $this->assertDatabaseCount('whatsapp_broadcast_recipients', 2);
        Bus::assertDispatched(BroadcastWhatsAppMessageJob::class);
    }

    public function test_send_text_uses_evolution_http_client(): void
    {
        app(WhatsAppSettingsService::class)->updateSettings([
            'evolution_base_url' => 'https://evolution.example.com',
            'evolution_api_key' => 'test-key',
            'evolution_instance_name' => 'main',
            'evolution_rotation_enabled' => false,
            'whatsapp_provider' => 'evolution',
        ]);

        EvolutionInstance::create([
            'instance_name' => 'main',
            'connection_status' => 'open',
            'is_default' => true,
            'rotation_enabled' => true,
        ]);

        Http::fake([
            'evolution.example.com/*' => Http::response([
                'key' => ['id' => 'msg-123'],
                'status' => 'PENDING',
            ], 200),
        ]);

        $this->actingAs($this->adminUser())
            ->post(route('admin.whatsapp-send.store'), [
                'to' => '966533333333',
                'text' => 'مرحبا',
                'sync' => true,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'evolution.example.com');
        });
    }
}
