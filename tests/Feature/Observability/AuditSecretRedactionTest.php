<?php

namespace Tests\Feature\Observability;

use App\Models\AiProvider;
use App\Models\AppStorageConfig;
use App\Models\EvolutionInstance;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The single most important test in the observability layer.
 *
 * Spatie logs *attribute values*, not raw database values. Every secret in this app
 * sits behind an `encrypted` cast or a `hashed` cast, so a careless `logAll()` /
 * `logFillable()` would decrypt them straight into activity_log.properties — turning
 * the audit trail into the worst vulnerability in the system.
 *
 * These tests assert the secret never appears anywhere in the log, in any form.
 */
class AuditSecretRedactionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every value stored in the activity_log, as raw JSON — the ground truth.
     */
    protected function rawActivityProperties(): string
    {
        return DB::table('activity_log')->pluck('properties')->implode("\n");
    }

    protected function assertNeverLogged(string $secret): void
    {
        $raw = $this->rawActivityProperties();

        $this->assertNotSame('', $raw, 'No activity was logged at all — the test proves nothing.');
        $this->assertStringNotContainsString($secret, $raw, "The secret [{$secret}] leaked into activity_log.properties.");
        $this->assertStringNotContainsString(base64_encode($secret), $raw, 'The secret leaked base64-encoded.');
    }

    public function test_smtp_password_never_reaches_the_activity_log(): void
    {
        $secret = 'sup3r-s3cret-smtp-pa55word';

        $setting = SmtpSetting::query()->create([
            'name' => 'Primary', 'host' => 'smtp.example.com', 'port' => 587,
            'username' => 'mailer', 'password' => 'initial-value', 'encryption' => 'tls',
            'from_address' => 'no-reply@example.com', 'from_name' => 'Test',
            'is_default' => true, 'is_active' => true,
        ]);

        $setting->update(['password' => $secret, 'host' => 'smtp.changed.com']);

        // Confirm the model really holds the secret — otherwise the assertion is vacuous.
        $this->assertSame($secret, $setting->fresh()->password);
        $this->assertNeverLogged($secret);
    }

    public function test_ai_provider_api_key_never_reaches_the_activity_log(): void
    {
        $secret = 'sk-live-0123456789abcdefghijklmnop';

        $provider = AiProvider::query()->create([
            'name' => 'OpenAI', 'driver' => 'openai', 'api_key' => 'initial-key',
            'base_url' => 'https://api.openai.com', 'default_model' => 'gpt-4',
            'is_default' => true, 'is_active' => true,
        ]);

        $provider->update(['api_key' => $secret, 'default_model' => 'gpt-5']);

        $this->assertSame($secret, $provider->fresh()->api_key);
        $this->assertNeverLogged($secret);
    }

    public function test_evolution_api_key_never_reaches_the_activity_log(): void
    {
        $secret = 'evolution-api-key-9f8e7d6c5b4a';

        $instance = EvolutionInstance::query()->create([
            'instance_name' => 'primary', 'connection_status' => 'open',
            'evolution_api_key' => 'initial-key', 'rotation_enabled' => true,
        ]);

        $instance->update(['evolution_api_key' => $secret, 'label' => 'Renamed']);

        $this->assertSame($secret, $instance->fresh()->decryptApiKey());
        $this->assertNeverLogged($secret);
    }

    public function test_storage_config_secrets_never_reach_the_activity_log(): void
    {
        $secret = 'AKIA-SECRET-ACCESS-KEY-VALUE';

        $config = AppStorageConfig::query()->create([
            'name' => 'S3 Primary',
            'driver' => 's3',
            'config' => ['key' => 'AKIAPUBLIC', 'secret_access_key' => 'initial', 'region' => 'us-east-1'],
            'is_active' => true,
            'priority' => 1,
        ]);

        $config->update([
            'config' => ['key' => 'AKIAPUBLIC', 'secret_access_key' => $secret, 'region' => 'eu-west-1'],
            'name' => 'S3 Renamed',
        ]);

        $this->assertNeverLogged($secret);
    }

    public function test_user_password_hash_never_reaches_the_activity_log(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $user->update(['password' => 'BrandNewPassword123!', 'name_ar' => 'اسم محدّث']);

        $raw = $this->rawActivityProperties();

        $this->assertStringNotContainsString($originalHash, $raw);
        $this->assertStringNotContainsString($user->fresh()->password, $raw);

        // The benign change WAS recorded, proving auditing is actually on and this
        // test is not passing simply because nothing is logged. Compared against the
        // decoded properties, since the column stores Arabic as escaped unicode.
        $latest = DB::table('activity_log')->orderByDesc('id')->first();
        $properties = json_decode($latest->properties, true);

        $this->assertSame('اسم محدّث', $properties['attributes']['name_ar']);
        $this->assertArrayNotHasKey('password', $properties['attributes']);
        $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
    }

    public function test_the_deny_list_strips_a_secret_even_if_a_whitelist_wrongly_includes_it(): void
    {
        // Simulate the human error the deny-list exists to catch.
        config()->set('observability.audit.models.'.SmtpSetting::class, [
            'log_name' => 'settings',
            'fields' => ['name', 'host', 'password'],
        ]);

        $this->assertNotContains('password', (new SmtpSetting)->auditedFields());
    }

    public function test_every_configured_whitelist_is_free_of_denied_fields(): void
    {
        $denied = config('observability.audit.never_log');

        foreach (config('observability.audit.models') as $model => $settings) {
            $overlap = array_intersect($settings['fields'], $denied);

            $this->assertSame(
                [],
                array_values($overlap),
                "Model [{$model}] whitelists denied field(s): ".implode(', ', $overlap)
            );
        }
    }
}
