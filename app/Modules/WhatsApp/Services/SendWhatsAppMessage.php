<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Support\WhatsAppRecipientNormalizer;

class SendWhatsAppMessage
{
    protected WhatsAppSettingsService $settingsService;

    public function __construct(WhatsAppSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Send text message.
     *
     * `$purpose` decides which provider handles it and, on Meta, which approved
     * template takes over once the 24-hour customer service window has closed.
     * `$templateParameters` fills that template's {{1}}, {{2}} … placeholders.
     *
     * @param  array<int, string>  $templateParameters
     */
    public function sendText(
        string $to,
        string $text,
        bool $previewUrl = false,
        ?WhatsAppPurpose $purpose = null,
        array $templateParameters = [],
    ): WhatsAppMessage {
        $purpose ??= WhatsAppPurpose::Manual;
        $provider = app(WhatsAppRouter::class)->providerFor($purpose);
        $normalizedRecipient = WhatsAppRecipientNormalizer::normalize($provider, $to);

        // Find or create contact
        $contact = WhatsAppContact::findOrCreateByWaId($normalizedRecipient);

        // Create message record with queued status
        $message = WhatsAppMessage::create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => $text,
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);

        // Dispatch job to send message
        SendWhatsAppMessageJob::dispatch($message, array_filter([
            'type' => 'text',
            'text' => $text,
            'preview_url' => $previewUrl,
            'purpose' => $purpose->value,
            'template_parameters' => $templateParameters,
        ], fn ($value) => $value !== []));

        return $message;
    }

    /**
     * Send text message synchronously (runs in the same request, no queue).
     * Use for welcome messages so they are sent immediately without needing queue:work.
     */
    public function sendTextSync(
        string $to,
        string $text,
        bool $previewUrl = false,
        bool $applySendDelay = true,
        ?string $evolutionInstanceName = null,
        ?WhatsAppPurpose $purpose = null,
    ): WhatsAppMessage {
        // Without a purpose this defaulted to Manual downstream, which silently made
        // campaigns ignore their own routing row — and on the official providers, sent
        // marketing as free text, which Meta rejects outright.
        $purpose ??= WhatsAppPurpose::Manual;
        $provider = app(WhatsAppRouter::class)->providerFor($purpose);
        $normalizedRecipient = WhatsAppRecipientNormalizer::normalize($provider, $to);
        $contact = WhatsAppContact::findOrCreateByWaId($normalizedRecipient);

        $payload = [];
        if ($evolutionInstanceName !== null && $evolutionInstanceName !== '') {
            $payload['evolution_instance_name'] = $evolutionInstanceName;
        }

        $message = WhatsAppMessage::create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => $text,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'payload' => $payload ?: null,
        ]);

        $messageData = [
            'type' => 'text',
            'text' => $text,
            'preview_url' => $previewUrl,
            'apply_send_delay' => $applySendDelay,
            'purpose' => $purpose->value,
        ];

        if ($evolutionInstanceName !== null && $evolutionInstanceName !== '') {
            $messageData['evolution_instance_name'] = $evolutionInstanceName;
        }

        try {
            app(WhatsAppOutboundSendService::class)->send($message, $messageData);
        } catch (\Throwable $e) {
            $message->update([
                'status' => WhatsAppMessage::STATUS_FAILED,
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ]);

            throw $e;
        }

        return $message->fresh();
    }

    /**
     * Send template message
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'ar',
        array $components = []
    ): WhatsAppMessage {
        $provider = $this->settingsService->getSettings()['whatsapp_provider'] ?? WhatsAppSettingsService::DEFAULT_PROVIDER;
        $normalizedRecipient = WhatsAppRecipientNormalizer::normalize($provider, $to);

        // Find or create contact
        $contact = WhatsAppContact::findOrCreateByWaId($normalizedRecipient);

        // Create message record with queued status
        $message = WhatsAppMessage::create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEMPLATE,
            'body' => $templateName,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'payload' => [
                'template_name' => $templateName,
                'language' => $language,
                'components' => $components,
            ],
        ]);

        // Dispatch job to send message
        SendWhatsAppMessageJob::dispatch($message, [
            'type' => 'template',
            'template_name' => $templateName,
            'language' => $language,
            'components' => $components,
        ]);

        return $message;
    }

    /**
     * Send document message
     */
    public function sendDocument(
        string $to,
        string $documentUrl,
        string $filename,
        ?string $caption = null
    ): WhatsAppMessage {
        $provider = $this->settingsService->getSettings()['whatsapp_provider'] ?? WhatsAppSettingsService::DEFAULT_PROVIDER;
        $normalizedRecipient = WhatsAppRecipientNormalizer::normalize($provider, $to);

        // Find or create contact
        $contact = WhatsAppContact::findOrCreateByWaId($normalizedRecipient);

        // Create message record with queued status
        $message = WhatsAppMessage::create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_DOCUMENT,
            'body' => $caption ?? $filename,
            'status' => WhatsAppMessage::STATUS_QUEUED,
            'payload' => [
                'document_url' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption,
            ],
        ]);

        // Dispatch job to send message
        SendWhatsAppMessageJob::dispatch($message, [
            'type' => 'document',
            'document_url' => $documentUrl,
            'filename' => $filename,
            'caption' => $caption,
        ]);

        return $message;
    }
}
