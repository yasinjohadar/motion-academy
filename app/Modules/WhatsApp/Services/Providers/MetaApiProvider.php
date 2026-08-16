<?php

namespace App\Modules\WhatsApp\Services\Providers;

use App\Modules\WhatsApp\DTOs\SendMessageResponseDTO;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Services\WhatsAppProviderService;
use App\Modules\WhatsApp\Support\WhatsAppRecipientNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Official WhatsApp Cloud API provider.
 *
 * Contract (verified against Meta's Cloud API reference):
 *   POST https://graph.facebook.com/{version}/{phone_number_id}/messages
 *   Authorization: Bearer {access_token}
 *   {"messaging_product":"whatsapp","recipient_type":"individual","to":…,"type":…}
 *   → 200 {"messages":[{"id":"wamid.…"}]}
 *
 * IMPORTANT — this provider is NOT interchangeable with Evolution. Meta enforces a
 * 24-hour customer service window: once it closes, only pre-approved templates are
 * accepted and free-form text is rejected. Callers that initiate conversations (OTP,
 * password reset, campaigns) must route through a template. See CustomerServiceWindow.
 */
class MetaApiProvider implements WhatsAppProviderService
{
    protected string $phoneNumberId;

    protected string $accessToken;

    protected string $apiVersion;

    protected int $timeout;

    public function __construct(array $config)
    {
        $this->phoneNumberId = (string) ($config['phone_number_id'] ?? '');
        $this->accessToken = (string) ($config['access_token'] ?? '');
        $this->apiVersion = (string) ($config['api_version'] ?? 'v21.0');
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public function sendText(string $to, string $text, bool $previewUrl = false): SendMessageResponseDTO
    {
        return $this->send([
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ],
        ], $to);
    }

    public function sendTemplate(string $to, string $templateName, string $language = 'ar', array $components = []): SendMessageResponseDTO
    {
        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if ($components !== []) {
            $template['components'] = $components;
        }

        return $this->send(['type' => 'template', 'template' => $template], $to);
    }

    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): SendMessageResponseDTO
    {
        $document = ['link' => $documentUrl, 'filename' => $filename];

        if (filled($caption)) {
            $document['caption'] = $caption;
        }

        return $this->send(['type' => 'document', 'document' => $document], $to);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'إعدادات Meta غير مكتملة — يلزم معرّف الرقم ورمز الوصول.'];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->get($this->nodeUrl(), ['fields' => 'display_phone_number,verified_name,quality_rating']);

            if ($response->failed()) {
                return ['success' => false, 'message' => $this->errorMessage($response)];
            }

            $number = $response->json('display_phone_number') ?? '—';
            $name = $response->json('verified_name') ?? '—';

            return ['success' => true, 'message' => "تم الاتصال بنجاح — {$name} ({$number})"];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'تعذّر الاتصال بـMeta: '.$e->getMessage()];
        }
    }

    public function isConfigured(): bool
    {
        return $this->phoneNumberId !== '' && $this->accessToken !== '';
    }

    /**
     * @param  array<string, mixed>  $typePayload
     */
    protected function send(array $typePayload, string $to): SendMessageResponseDTO
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppApiException('إعدادات Meta غير مكتملة — يلزم معرّف الرقم ورمز الوصول.');
        }

        // Meta expects a plain international number; the shared normaliser's default
        // branch already produces E.164.
        $recipient = ltrim(WhatsAppRecipientNormalizer::normalize('meta', $to), '+');

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            ...$typePayload,
        ];

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->asJson()
                ->post($this->messagesUrl(), $payload);
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر الاتصال بـMeta: '.$e->getMessage(), 0, null, [
                'type' => $typePayload['type'] ?? null,
            ]);
        }

        if ($response->failed()) {
            $this->logFailure($response, $payload);

            throw new WhatsAppApiException(
                $this->errorMessage($response),
                (int) ($response->json('error.code') ?? $response->status()),
                null,
                [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                    'type' => $typePayload['type'] ?? null,
                ]
            );
        }

        $messageId = $response->json('messages.0.id');

        if (! is_string($messageId) || $messageId === '') {
            throw new WhatsAppApiException('استجابة Meta لا تحتوي على معرّف رسالة.', 0, null, [
                'response' => $response->json(),
            ]);
        }

        return new SendMessageResponseDTO($messageId, $response->json() ?? []);
    }

    protected function messagesUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
    }

    protected function nodeUrl(): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    /**
     * Meta nests the useful part under `error`; `error_user_msg` is the human-facing
     * text when present and is far more actionable than the generic message.
     */
    protected function errorMessage(Response $response): string
    {
        $error = $response->json('error');

        if (! is_array($error)) {
            return 'فشل طلب Meta برمز '.$response->status();
        }

        $message = $error['error_user_msg']
            ?? $error['error_data']['details']
            ?? $error['message']
            ?? 'خطأ غير معروف من Meta';

        $code = $error['code'] ?? null;

        return $code ? "{$message} (رمز {$code})" : (string) $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function logFailure(Response $response, array $payload): void
    {
        Log::channel('whatsapp')->error('Meta Cloud API send failed', [
            'status' => $response->status(),
            'error' => $response->json('error'),
            'type' => $payload['type'] ?? null,
            // The recipient is logged, the message body deliberately is not.
            'to' => $payload['to'] ?? null,
        ]);
    }
}
