<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhookEventJob;
use App\Models\EvolutionInstance;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\Evolution\EvolutionWebhookParser;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookController extends Controller
{
    public function __construct(
        private WhatsAppSettingsService $settingsService,
        private EvolutionWebhookParser $evolutionParser
    ) {}

    public function handle(Request $request, ?string $instance = null)
    {
        if (! $this->verifyRequest($request)) {
            Log::channel('whatsapp')->warning('Evolution webhook rejected: unauthorized', [
                'instance' => $instance,
                'ip' => $request->ip(),
                'has_apikey_header' => $request->header('apikey') !== null,
            ]);

            return response('Unauthorized', 401);
        }

        try {
            $payload = $request->json()->all();
            $instanceName = $instance ?: (string) ($payload['instance'] ?? '');

            // Instance from the webhook URL is authoritative (Evolution body may omit or differ).
            if ($instanceName !== '') {
                $payload['instance'] = $instanceName;
            }

            if ($this->evolutionParser->isConnectionUpdate($payload) && $instanceName !== '') {
                $this->updateInstanceConnection($instanceName, $payload);
            }

            $eventId = $this->generateEventId($payload, $instanceName);

            $existingEvent = WhatsAppWebhookEvent::where('event_id', $eventId)->first();
            if ($existingEvent && $existingEvent->isProcessed()) {
                return response('OK', 200);
            }

            $webhookEvent = WhatsAppWebhookEvent::firstOrCreate(
                ['event_id' => $eventId],
                ['payload' => $payload]
            );

            ProcessWhatsAppWebhookEventJob::dispatch($webhookEvent)->onQueue('whatsapp');

            Log::channel('whatsapp')->info('Evolution webhook queued', [
                'event_id' => $eventId,
                'instance' => $instanceName,
                'event' => $payload['event'] ?? null,
            ]);

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Evolution webhook error', [
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    protected function verifyRequest(Request $request): bool
    {
        $settings = $this->settingsService->getSettings();
        $apiKey = (string) ($settings['evolution_api_key'] ?? '');
        $webhookSecret = (string) ($settings['evolution_webhook_secret'] ?? '');

        $headerKey = (string) $request->header('apikey', '');
        if ($headerKey !== '' && $apiKey !== '' && hash_equals($apiKey, $headerKey)) {
            return true;
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            if ($webhookSecret !== '' && hash_equals($webhookSecret, $bearer)) {
                return true;
            }
            if ($apiKey !== '' && hash_equals($apiKey, $bearer)) {
                return true;
            }
        }

        if ($apiKey === '' && $webhookSecret === '') {
            return true;
        }

        return false;
    }

    protected function generateEventId(array $payload, string $instanceName): string
    {
        $parts = [
            $instanceName ?: 'unknown',
            (string) ($payload['event'] ?? 'event'),
            (string) data_get($payload, 'data.key.id'),
            (string) data_get($payload, 'data.messageTimestamp'),
            (string) data_get($payload, 'data.state'),
            md5(json_encode($payload)),
        ];

        return hash('sha256', implode('|', $parts));
    }

    protected function updateInstanceConnection(string $instanceName, array $payload): void
    {
        $state = $this->evolutionParser->extractConnectionState($payload);
        if (! $state) {
            return;
        }

        EvolutionInstance::where('instance_name', $instanceName)->update([
            'connection_status' => $state === 'open' ? 'open' : $state,
            'connected_at' => $state === 'open' ? now() : null,
            'disconnected_at' => $state === 'open' ? null : now(),
        ]);
    }
}
