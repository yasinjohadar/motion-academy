<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Guards the direction of dependencies between the core and the optional modules.
 *
 * The point of a starter kit is that a new project can delete a module it does not
 * need. That is only true if nothing in the core imports the module — and today
 * several core classes do. Physically moving the files into app/Modules would not
 * change that: the core would simply import App\Modules\WhatsApp\… instead, and the
 * module would still be undeletable.
 *
 * So this test does not demand zero coupling. It freezes the coupling that exists
 * right now and fails on anything new. Every dependency we invert comes out of
 * BASELINE, and the day the list is empty the module is genuinely removable.
 */
class ModuleBoundaryTest extends TestCase
{
    /**
     * Core → module imports that exist today. Shrink this list; never grow it.
     *
     * @var array<string, list<string>>
     */
    private const BASELINE = [
        // Enums/NotificationChannel.php — inverted: the channel class now comes from
        // config('notifications.drivers'), so the core no longer names it.
        'Notifications/BaseNotification.php' => [
            'App\Enums\WhatsAppPurpose',
        ],
        'Notifications/Auth/ResetPasswordNotification.php' => [
            'App\Enums\WhatsAppPurpose',
        ],
        'Support/NotificationChannelResolver.php' => [
            'App\Models\EvolutionInstance',
            'App\Services\WhatsApp\ProviderCapabilities',
            'App\Services\WhatsApp\WhatsAppProviderFactory',
            'App\Services\WhatsApp\WhatsAppSettingsService',
        ],
        'Services/Otp/OtpService.php' => [
            'App\Enums\WhatsAppPurpose',
            'App\Services\WhatsApp\SendWhatsAppMessage',
        ],
        'Services/Dashboard/DashboardStats.php' => [
            'App\Models\EvolutionInstance',
            'App\Models\WhatsAppMessage',
        ],
        'Http/Controllers/Admin/ObservabilityController.php' => [
            'App\Models\WhatsAppWebhookEvent',
        ],
    ];

    /**
     * Classes that make up the WhatsApp module, wherever they currently sit.
     *
     * @var list<string>
     */
    private const MODULE_PREFIXES = [
        'App\Services\WhatsApp\\',
        'App\Models\WhatsApp',
        'App\Models\EvolutionInstance',
        'App\Enums\WhatsAppPurpose',
        'App\DTOs\WhatsApp\\',
        'App\Policies\WhatsApp',
        'App\Notifications\WhatsApp\\',
        'App\Notifications\Channels\WhatsAppChannel',
        'App\Http\Controllers\Admin\WhatsApp',
        'App\Http\Controllers\Api\EvolutionWebhookController',
        'App\Http\Controllers\Api\MetaWebhookController',
        'App\Support\WhatsAppRecipientNormalizer',
        'App\Support\WhatsAppSendErrorMessage',
        'App\Events\WhatsAppMessageReceived',
        'App\Listeners\NotifyOnWhatsAppMessageReceived',
        'App\Exceptions\WhatsAppApiException',
        'App\Jobs\SendWhatsAppMessageJob',
        'App\Jobs\ProcessWhatsAppWebhookEventJob',
        'App\Jobs\BroadcastWhatsAppMessageJob',
        'App\Jobs\PollFlaxxaMessageStatusJob',
    ];

    /**
     * A file is part of the module (not core) when it declares one of those classes.
     */
    private function isModuleFile(string $relativePath): bool
    {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

        foreach (self::MODULE_PREFIXES as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isModuleClass(string $class): bool
    {
        foreach (self::MODULE_PREFIXES as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function currentCoupling(): array
    {
        $found = [];
        $root = app_path();

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if ($this->isModuleFile($relative)) {
                continue;
            }

            preg_match_all('/^use (App\\\\[^;\s]+)/m', file_get_contents($file->getPathname()), $matches);

            $imports = array_values(array_filter(
                array_unique($matches[1]),
                fn (string $class): bool => $this->isModuleClass($class)
            ));

            if ($imports !== []) {
                sort($imports);
                $found[$relative] = $imports;
            }
        }

        ksort($found);

        return $found;
    }

    public function test_no_new_dependency_from_the_core_into_the_whatsapp_module(): void
    {
        $baseline = self::BASELINE;
        ksort($baseline);

        foreach ($baseline as $file => $imports) {
            sort($imports);
            $baseline[$file] = $imports;
        }

        $current = $this->currentCoupling();

        $added = [];
        foreach ($current as $file => $imports) {
            foreach ($imports as $import) {
                if (! in_array($import, $baseline[$file] ?? [], true)) {
                    $added[] = "{$file} → {$import}";
                }
            }
        }

        $this->assertSame([], $added, implode("\n", [
            'New core → module dependencies were introduced.',
            'The core must not reach into an optional module; invert the dependency',
            'or move the file into the module. New couplings:',
            ...$added,
        ]));
    }

    public function test_the_baseline_does_not_claim_coupling_that_is_already_gone(): void
    {
        $current = $this->currentCoupling();

        $stale = [];
        foreach (self::BASELINE as $file => $imports) {
            foreach ($imports as $import) {
                if (! in_array($import, $current[$file] ?? [], true)) {
                    $stale[] = "{$file} → {$import}";
                }
            }
        }

        // Keeps the list honest: once a dependency is inverted it must be deleted from
        // BASELINE, otherwise the number stops meaning anything.
        $this->assertSame([], $stale, implode("\n", [
            'These baseline entries no longer exist — remove them from BASELINE:',
            ...$stale,
        ]));
    }

    public function test_the_scanner_actually_finds_something(): void
    {
        // Without this, a broken regex would make both tests above pass vacuously.
        $this->assertNotEmpty($this->currentCoupling());
        $this->assertArrayHasKey('Services/Otp/OtpService.php', $this->currentCoupling());
    }
}
