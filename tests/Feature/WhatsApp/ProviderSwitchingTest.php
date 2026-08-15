<?php

namespace Tests\Feature\WhatsApp;

use App\Services\WhatsApp\Evolution\EvolutionRotatingSendService;
use App\Services\WhatsApp\Providers\EvolutionApiProvider;
use App\Services\WhatsApp\Providers\MetaApiProvider;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The foundation everything else rests on.
 *
 * Provider switching looked supported — an interface, a factory, a `whatsapp_provider`
 * key — but getSettings() returned the literal 'evolution' and never read storage, so
 * the setting was decorative. These tests fail against that original code.
 */
class ProviderSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(): WhatsAppSettingsService
    {
        return app(WhatsAppSettingsService::class);
    }

    public function test_the_provider_defaults_to_evolution(): void
    {
        $this->assertSame('evolution', $this->settings()->getSettings()['whatsapp_provider']);
    }

    public function test_changing_the_stored_setting_actually_changes_the_provider(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'meta']);

        // Previously impossible: the value was hard-coded in getSettings().
        $this->assertSame('meta', $this->settings()->getSettings()['whatsapp_provider']);
    }

    public function test_an_unknown_stored_provider_falls_back_to_evolution(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'not-a-provider']);

        // A typo in the settings table must not take WhatsApp down entirely.
        $this->assertSame('evolution', $this->settings()->getSettings()['whatsapp_provider']);
    }

    public function test_provider_config_returns_evolution_keys_for_evolution(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'evolution',
            'evolution_base_url' => 'https://evo.example.com',
            'evolution_api_key' => 'evo-key',
            'evolution_instance_name' => 'primary',
        ]);

        $config = $this->settings()->getProviderConfig();

        $this->assertSame('https://evo.example.com', $config['base_url']);
        $this->assertSame('evo-key', $config['api_key']);
        $this->assertSame('primary', $config['instance_name']);
    }

    public function test_provider_config_returns_meta_keys_for_meta(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'meta',
            'meta_phone_number_id' => '123456789',
            'meta_access_token' => 'EAAG-token',
            'meta_api_version' => 'v21.0',
        ]);

        $config = $this->settings()->getProviderConfig();

        // getProviderConfig() used to hand Evolution keys to every provider.
        $this->assertSame('123456789', $config['phone_number_id']);
        $this->assertSame('EAAG-token', $config['access_token']);
        $this->assertSame('v21.0', $config['api_version']);
    }

    public function test_the_factory_builds_both_providers(): void
    {
        $evolution = WhatsAppProviderFactory::create('evolution', [
            'base_url' => 'https://evo.example.com', 'api_key' => 'k', 'instance_name' => 'i',
        ]);

        $meta = WhatsAppProviderFactory::create('meta', [
            'phone_number_id' => '1', 'access_token' => 't', 'api_version' => 'v21.0',
        ]);

        $this->assertInstanceOf(EvolutionApiProvider::class, $evolution);
        $this->assertInstanceOf(MetaApiProvider::class, $meta);
    }

    public function test_the_factory_still_rejects_an_unknown_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WhatsAppProviderFactory::create('carrier-pigeon', []);
    }

    public function test_meta_secrets_are_encrypted_at_rest(): void
    {
        $this->settings()->updateSettings([
            'meta_access_token' => 'EAAG-super-secret',
            'meta_app_secret' => 'app-secret-value',
            'meta_verify_token' => 'verify-secret',
        ]);

        $stored = \DB::table('system_settings')
            ->where('group', 'whatsapp')
            ->whereIn('key', ['meta_access_token', 'meta_app_secret', 'meta_verify_token'])
            ->pluck('value')
            ->implode('|');

        foreach (['EAAG-super-secret', 'app-secret-value', 'verify-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored, "[{$secret}] is stored in clear text.");
        }

        // ...but they still round-trip for use.
        $settings = $this->settings()->getSettings();
        $this->assertSame('EAAG-super-secret', $settings['meta_access_token']);
        $this->assertSame('app-secret-value', $settings['meta_app_secret']);
    }

    public function test_the_evolution_rotation_service_stands_down_for_other_providers(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'meta']);

        $rotator = app(EvolutionRotatingSendService::class);

        // Number rotation is an Evolution concept; Meta has one phone number id.
        $this->assertFalse($rotator->isRotationActive());
    }
}
