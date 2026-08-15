<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppPurpose;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.whatsapp-settings.list', 'admin.whatsapp-settings.edit'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'whatsapp_provider' => 'evolution',
            'evolution_base_url' => 'https://evo.example.com',
            'delay_between_messages' => 3,
            'delay_between_broadcasts' => 5,
            'max_messages_per_minute' => 20,
            'min_delay' => 2,
            'max_delay' => 5,
        ], $overrides);
    }

    protected function settings(): WhatsAppSettingsService
    {
        return app(WhatsAppSettingsService::class);
    }

    // ------------------------------------------------------------------ provider switching

    public function test_saving_the_form_can_switch_the_provider_to_meta(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'meta_phone_number_id' => '123456789',
                'meta_access_token' => 'EAAG-token',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // The controller used to force 'evolution' on every save, which silently
        // undid the choice the moment the operator pressed the button.
        $this->assertSame('meta', $this->settings()->getSettings()['whatsapp_provider']);
    }

    public function test_a_meta_only_install_can_save_without_an_evolution_url(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'evolution_base_url' => '',
                'meta_phone_number_id' => '123456789',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('meta', $this->settings()->getSettings()['whatsapp_provider']);
    }

    public function test_evolution_still_requires_its_base_url(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload(['evolution_base_url' => '']))
            ->assertSessionHasErrors('evolution_base_url');
    }

    public function test_meta_requires_a_phone_number_id(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'meta_phone_number_id' => '',
            ]))
            ->assertSessionHasErrors('meta_phone_number_id');
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload(['whatsapp_provider' => 'twilio']))
            ->assertSessionHasErrors('whatsapp_provider');
    }

    public function test_a_malformed_api_version_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'meta_phone_number_id' => '1',
                'meta_api_version' => '21',
            ]))
            ->assertSessionHasErrors('meta_api_version');
    }

    public function test_saving_the_form_can_switch_the_provider_to_flaxxa(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'flaxxa',
                'flaxxa_base_url' => 'https://wapi.flaxxa.com',
                'flaxxa_token' => 'brand-token',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('flaxxa', $this->settings()->getSettings()['whatsapp_provider']);
        $this->assertSame('brand-token', $this->settings()->getSettings()['flaxxa_token']);
    }

    public function test_flaxxa_requires_its_base_url(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'flaxxa',
                'flaxxa_base_url' => '',
            ]))
            ->assertSessionHasErrors('flaxxa_base_url');
    }

    public function test_the_flaxxa_base_url_falls_back_to_the_hosted_service(): void
    {
        // Evolution installs never touch the field, and an empty base URL would make
        // the provider unusable the moment someone switched to it.
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            WhatsAppSettingsService::DEFAULT_FLAXXA_BASE_URL,
            $this->settings()->getSettings()['flaxxa_base_url']
        );
    }

    // ------------------------------------------------------------------ secrets

    public function test_secrets_never_reach_the_browser(): void
    {
        $this->settings()->updateSettings([
            'meta_access_token' => 'super-secret-token',
            'meta_app_secret' => 'super-secret-app',
            'meta_verify_token' => 'super-secret-verify',
            'evolution_api_key' => 'super-secret-key',
            'flaxxa_token' => 'super-secret-flaxxa',
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.whatsapp-settings.index'));

        $response->assertOk();

        foreach ([
            'super-secret-token', 'super-secret-app', 'super-secret-verify',
            'super-secret-key', 'super-secret-flaxxa',
        ] as $secret) {
            $response->assertDontSee($secret, escape: false);
        }

        // Presence is still reported, so the UI can say "saved" without the value.
        $response->assertInertia(fn ($page) => $page
            ->where('settings.has_meta_access_token', true)
            ->where('settings.has_meta_app_secret', true)
            ->where('settings.has_meta_verify_token', true)
            ->where('settings.has_flaxxa_token', true));
    }

    public function test_an_empty_secret_field_keeps_the_stored_value(): void
    {
        $this->settings()->updateSettings(['meta_access_token' => 'keep-me']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'meta_phone_number_id' => '1',
                'meta_access_token' => '',
            ]))
            ->assertSessionHasNoErrors();

        // The field is write-only, so blank means "unchanged" — not "erase it".
        $this->assertSame('keep-me', $this->settings()->getSettings()['meta_access_token']);
    }

    public function test_a_submitted_secret_replaces_the_stored_one_and_stays_encrypted(): void
    {
        $this->settings()->updateSettings(['meta_access_token' => 'old-token']);

        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'whatsapp_provider' => 'meta',
                'meta_phone_number_id' => '1',
                'meta_access_token' => 'new-token',
            ]));

        $this->assertSame('new-token', $this->settings()->getSettings()['meta_access_token']);

        $stored = DB::table('system_settings')->where('key', 'meta_access_token')->value('value');

        $this->assertNotSame('new-token', $stored);
    }

    // ------------------------------------------------------------------ routing matrix

    public function test_the_routing_matrix_is_saved(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'routing' => [
                    'otp' => ['provider' => 'meta', 'template' => ['name' => 'otp_code', 'language' => 'ar']],
                    'campaign' => ['provider' => 'evolution', 'template' => ['name' => '']],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stored = $this->settings()->getRoutingOverrides();

        $this->assertSame('meta', $stored['otp']['provider']);
        $this->assertSame('otp_code', $stored['otp']['template']['name']);
        $this->assertSame('evolution', $stored['campaign']['provider']);
        $this->assertNull($stored['campaign']['template']['name']);
    }

    public function test_the_matrix_cannot_smuggle_unknown_keys_into_settings(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'routing' => [
                    'otp' => ['provider' => 'meta', 'template' => ['name' => 'otp_code']],
                    'meta_access_token' => ['provider' => 'meta'],
                    'nonsense' => ['provider' => 'meta'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $stored = $this->settings()->getRoutingOverrides();

        // Only real purposes survive — the matrix is a nested array straight from the
        // browser and must not become a write primitive for arbitrary settings.
        $this->assertArrayNotHasKey('nonsense', $stored);
        $this->assertArrayNotHasKey('meta_access_token', $stored);
        $this->assertArrayHasKey('otp', $stored);
    }

    public function test_an_unknown_routing_provider_is_rejected(): void
    {
        $this->actingAs($this->adminUser())
            ->put(route('admin.whatsapp-settings.update'), $this->payload([
                'routing' => ['otp' => ['provider' => 'carrier-pigeon']],
            ]))
            ->assertSessionHasErrors('routing.otp.provider');
    }

    // ------------------------------------------------------------------ screen payload

    public function test_the_screen_exposes_what_the_operator_needs_to_configure_meta(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.whatsapp-settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('metaWebhookUrl', route('api.webhooks.meta'))
                ->has('routing.otp')
                ->has('purposes', count(WhatsAppPurpose::cases()))
                ->etc());
    }

    // ------------------------------------------------------------------ template listing

    public function test_the_template_endpoint_lists_only_approved_templates(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'templates' => [
            ['name' => 'otp_code', 'status' => 'APPROVED', 'language' => 'ar', 'category' => 'AUTHENTICATION'],
            ['name' => 'draft', 'status' => 'PENDING', 'language' => 'ar', 'category' => 'MARKETING'],
        ]])]);

        $this->settings()->updateSettings(['flaxxa_token' => 'brand-token']);

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.whatsapp-settings.templates', ['provider' => 'flaxxa']))
            ->assertOk()
            ->assertJsonCount(1, 'templates')
            ->assertJsonPath('templates.0.name', 'otp_code');
    }

    public function test_a_provider_without_template_listing_returns_an_empty_list(): void
    {
        $this->actingAs($this->adminUser())
            ->getJson(route('admin.whatsapp-settings.templates', ['provider' => 'meta']))
            ->assertOk()
            ->assertJsonPath('templates', []);
    }

    public function test_a_dead_provider_does_not_break_the_settings_screen(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid API Token.'], 401)]);

        $this->settings()->updateSettings(['flaxxa_token' => 'bad-token']);

        // The field degrades to free text; the operator can still save everything else.
        $this->actingAs($this->adminUser())
            ->getJson(route('admin.whatsapp-settings.templates', ['provider' => 'flaxxa']))
            ->assertOk()
            ->assertJsonPath('templates', []);
    }

    public function test_the_template_endpoint_needs_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.whatsapp-settings.templates'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ capabilities payload

    public function test_the_screen_tells_the_ui_which_providers_enforce_the_window(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.whatsapp-settings.index'))
            ->assertInertia(fn ($page) => $page
                ->where('providerCapabilities.evolution.enforces_service_window', false)
                ->where('providerCapabilities.meta.enforces_service_window', true)
                ->where('providerCapabilities.flaxxa.enforces_service_window', true)
                ->etc());
    }

    // ------------------------------------------------------------------ connection test

    public function test_testing_meta_without_credentials_explains_itself(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.whatsapp-settings.test'), ['provider' => 'meta'])
            ->assertSessionHas('error');
    }

    public function test_an_unknown_provider_cannot_be_tested(): void
    {
        $this->actingAs($this->adminUser())
            ->post(route('admin.whatsapp-settings.test'), ['provider' => 'twilio'])
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------ authorization

    public function test_a_user_without_the_edit_permission_cannot_save(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.whatsapp-settings.update'), $this->payload())
            ->assertForbidden();
    }
}
