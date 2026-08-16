<?php

namespace App\Modules\WhatsApp\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Jobs\ProcessWhatsAppWebhookEventJob;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta Cloud API webhook.
 *
 * Two verified behaviours from Meta's docs:
 *   GET  — echo `hub.challenge` when `hub.verify_token` matches.
 *   POST — validate `X-Hub-Signature-256: sha256=<hmac_sha256(raw_body, app_secret)>`.
 *
 * The signature is computed over the RAW request body. Re-encoding the parsed JSON
 * produces different bytes (key order, unicode escaping, spacing) and would reject
 * every legitimate delivery.
 */
class MetaWebhookController extends Controller
{
    public function __construct(private WhatsAppSettingsService $settingsService) {}

    /**
     * Meta's subscription handshake.
     */
    public function verify(Request $request)
    {
        $settings = $this->settingsService->getSettings();
        $expected = (string) $settings['meta_verify_token'];

        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($expected === '' || $mode !== 'subscribe' || ! hash_equals($expected, $token)) {
            Log::channel('whatsapp')->warning('Meta webhook verification rejected', [
                'ip' => $request->ip(),
                'mode' => $mode,
                'token_configured' => $expected !== '',
            ]);

            return response('Forbidden', 403);
        }

        // Must be echoed as a bare body, not JSON.
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request)
    {
        if (! $this->signatureIsValid($request)) {
            Log::channel('whatsapp')->warning('Meta webhook rejected: bad signature', [
                'ip' => $request->ip(),
                'has_signature' => $request->hasHeader('X-Hub-Signature-256'),
            ]);

            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $eventId = $this->generateEventId($payload);

        $existing = WhatsAppWebhookEvent::query()->where('event_id', $eventId)->first();

        // Meta retries until it gets a 200; acknowledging an already-processed event
        // keeps retries from re-running side effects.
        if ($existing && $existing->isProcessed()) {
            return response('OK', 200);
        }

        $event = WhatsAppWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            ['payload' => $payload, 'provider' => 'meta'],
        );

        ProcessWhatsAppWebhookEventJob::dispatch($event)->onQueue('whatsapp');

        return response('OK', 200);
    }

    protected function signatureIsValid(Request $request): bool
    {
        $secret = (string) $this->settingsService->getSettings()['meta_app_secret'];

        if ($secret === '') {
            // Refuse rather than accept unsigned traffic: this endpoint is public.
            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    /**
     * Meta does not send a delivery id, so derive a stable one from the payload
     * contents for idempotency.
     */
    protected function generateEventId(array $payload): string
    {
        $ids = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $ids[] = 'm:'.($message['id'] ?? '');
                }
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $ids[] = 's:'.($status['id'] ?? '').':'.($status['status'] ?? '');
                }
            }
        }

        if ($ids === []) {
            return 'meta:'.hash('sha256', json_encode($payload));
        }

        sort($ids);

        return 'meta:'.hash('sha256', implode('|', $ids));
    }
}
