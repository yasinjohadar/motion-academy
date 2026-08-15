<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppPurpose;
use App\Exceptions\WhatsAppApiException;
use App\Jobs\PollFlaxxaMessageStatusJob;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\Evolution\EvolutionRotatingSendService;
use App\Services\WhatsApp\Evolution\EvolutionService;
use App\Support\WhatsAppRecipientNormalizer;

/**
 * Single place for outbound send logic (used by queue job and sync paths).
 * Avoids dispatching SendWhatsAppMessageJob twice when broadcast uses sync send.
 */
class WhatsAppOutboundSendService
{
    public function __construct(
        private WhatsAppSettingsService $settingsService,
        private EvolutionRotatingSendService $rotatingSendService,
        private EvolutionService $evolutionService,
        private WhatsAppRouter $router,
    ) {}

    /**
     * Perform API send and update the WhatsAppMessage row.
     *
     * @throws WhatsAppApiException|\Throwable
     */
    public function send(WhatsAppMessage $message, array $messageData = []): void
    {
        $contact = $message->contact;
        if (! $contact) {
            $message->load('contact');
            $contact = $message->contact;
        }

        $settings = $this->settingsService->getSettings();

        // The purpose picks the provider, so campaigns and OTP can run on different
        // providers at the same time.
        $purpose = WhatsAppPurpose::tryFrom((string) ($messageData['purpose'] ?? ''))
            ?? WhatsAppPurpose::Manual;

        $provider = $this->router->providerFor($purpose);
        $config = $this->settingsService->getProviderConfig($provider);
        $to = WhatsAppRecipientNormalizer::normalize($provider, $contact->wa_id);
        $messageType = $messageData['type'] ?? $message->type;

        // On Meta, a closed customer service window means free text is rejected —
        // swap to the purpose's approved template instead of failing at the API.
        if ($messageType === 'text') {
            $mode = $this->router->resolveSendMode($purpose, $contact);

            if ($mode['mode'] === 'template') {
                $template = $mode['template']->withBodyParameters(
                    $messageData['template_parameters'] ?? [$messageData['text'] ?? (string) $message->body]
                );

                $messageType = 'template';
                $messageData = [
                    ...$messageData,
                    'type' => 'template',
                    'template_name' => $template->name,
                    'language' => $template->language,
                    'components' => $template->components(),
                ];
            }
        }

        $forcedInstance = $messageData['evolution_instance_name']
            ?? ($message->payload['evolution_instance_name'] ?? null);
        $applyDelay = $messageData['apply_send_delay'] ?? true;

        if ($provider === 'evolution') {
            $sendResult = $this->rotatingSendService->sendWithRotation(
                function (string $instanceName) use ($to, $messageType, $messageData, $message) {
                    $instanceConfig = $this->evolutionService->providerConfigForInstance($instanceName);
                    $providerInstance = WhatsAppProviderFactory::create('evolution', $instanceConfig);

                    return $this->dispatchToProvider($providerInstance, $messageType, $to, $messageData, $message);
                },
                $forcedInstance ? (string) $forcedInstance : null,
                (bool) $applyDelay,
            );

            $response = $sendResult['result'];
            $usedInstance = $sendResult['instance_name'];
        } else {
            $providerInstance = WhatsAppProviderFactory::create($provider, $config);
            $response = $this->dispatchToProvider($providerInstance, $messageType, $to, $messageData, $message);
            $usedInstance = null;
        }

        $payload = array_merge($message->payload ?? [], [
            'response' => $response->rawResponse,
        ]);

        if ($usedInstance !== null) {
            $payload['evolution_instance_name'] = $usedInstance;
        }

        $message->update([
            'meta_message_id' => $response->metaMessageId,
            'provider' => $provider,
            'status' => WhatsAppMessage::STATUS_SENT,
            'payload' => $payload,
        ]);

        $this->scheduleStatusPolling($provider, $message, $response->rawResponse);
    }

    /**
     * Flaxxa never tells us what happened to a message, so we have to ask.
     *
     * Off by config for large campaigns: each poll spends one of the 60 requests per
     * minute the token allows, and a thousand-recipient run would drown in them.
     *
     * @param  array<string, mixed>  $rawResponse
     */
    private function scheduleStatusPolling(string $provider, WhatsAppMessage $message, array $rawResponse): void
    {
        if ($provider !== 'flaxxa' || ! config('whatsapp.flaxxa.poll_status', true)) {
            return;
        }

        // Flaxxa's own numeric id — the wamid is not accepted by their status endpoint.
        $flaxxaMessageId = $rawResponse['message_id'] ?? null;

        if (! is_numeric($flaxxaMessageId)) {
            return;
        }

        PollFlaxxaMessageStatusJob::dispatch($message, (int) $flaxxaMessageId)
            ->delay(now()->addSeconds((int) config('whatsapp.flaxxa.status_poll_delay_seconds', 15)));
    }

    private function dispatchToProvider(
        WhatsAppProviderService $providerInstance,
        string $messageType,
        string $to,
        array $messageData,
        WhatsAppMessage $message
    ) {
        if ($messageType === 'template') {
            return $providerInstance->sendTemplate(
                $to,
                $messageData['template_name'] ?? $message->body,
                $messageData['language'] ?? 'ar',
                $messageData['components'] ?? []
            );
        }

        if ($messageType === 'document') {
            return $providerInstance->sendDocument(
                $to,
                $messageData['document_url'] ?? '',
                $messageData['filename'] ?? 'document.pdf',
                $messageData['caption'] ?? null
            );
        }

        return $providerInstance->sendText(
            $to,
            $messageData['text'] ?? $message->body ?? '',
            $messageData['preview_url'] ?? false
        );
    }
}
