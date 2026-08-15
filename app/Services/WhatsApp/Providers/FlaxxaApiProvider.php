<?php

namespace App\Services\WhatsApp\Providers;

use App\DTOs\WhatsApp\SendMessageResponseDTO;
use App\Exceptions\WhatsAppApiException;
use App\Services\WhatsApp\WhatsAppProviderService;
use App\Support\WhatsAppRecipientNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flaxxa Wapi — a hosted layer over Meta's Cloud API.
 *
 * Authentication is a single brand token, but where it goes depends on the endpoint's
 * content type: JSON body for POST-JSON, query string for GET, form field for
 * multipart. That is Flaxxa's contract, not a choice made here.
 *
 * Two behaviours worth knowing before changing this file:
 *
 *  - **A 200 can still be a failure.** Flaxxa answers with `{"status": "..."}` and the
 *    documented success value is not guaranteed on every endpoint, so `$response->failed()`
 *    alone would record failed sends as sent. Everything goes through {@see assertOk()}.
 *  - **Meta's rules still apply.** Flaxxa is a BSP, so the 24-hour customer service
 *    window governs it — but it exposes no documented inbound webhook, so we cannot
 *    observe the window. See ProviderCapabilities and WhatsAppRouter::requiresTemplate.
 */
class FlaxxaApiProvider implements WhatsAppProviderService
{
    protected string $baseUrl;

    protected string $token;

    protected int $timeout;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->token = (string) ($config['token'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    public function sendText(string $to, string $text, bool $previewUrl = false): SendMessageResponseDTO
    {
        // Flaxxa has no link-preview switch; the field is accepted by our interface for
        // parity with the other providers and simply has no effect here.
        return $this->postJson('/api/v1/sendmessage', [
            'phone' => $this->recipient($to),
            'message' => $text,
        ], 'text');
    }

    public function sendTemplate(string $to, string $templateName, string $language = 'ar', array $components = []): SendMessageResponseDTO
    {
        $payload = [
            'phone' => $this->recipient($to),
            'template_name' => $templateName,
            'template_language' => $language,
        ];

        // Flaxxa passes `components` straight through to Meta, so MetaTemplate's output
        // is already the right shape.
        if ($components !== []) {
            $payload['components'] = $components;
        }

        return $this->postJson('/api/v1/sendtemplatemessage', $payload, 'template');
    }

    /**
     * Flaxxa accepts no document URL — only an uploaded file — so the link has to be
     * fetched and forwarded as multipart. Guarded by a size cap so a bad URL cannot
     * exhaust the worker's memory.
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): SendMessageResponseDTO
    {
        $this->ensureConfigured();

        $contents = $this->downloadAttachment($documentUrl);

        $form = Http::timeout($this->timeout)
            ->attach('header_attachment', $contents, $filename);

        try {
            $response = $form->post($this->url('/api/v1/sendmessagewithattachment'), array_filter([
                'token' => $this->token,
                'phone' => $this->recipient($to),
                'message' => $caption,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر الاتصال بـFlaxxa: '.$e->getMessage(), 0, null, [
                'type' => 'document',
            ]);
        }

        return $this->toDto($this->assertOk($response, 'document'));
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'إعدادات Flaxxa غير مكتملة — يلزم رابط الخدمة ورمز الوصول.'];
        }

        try {
            $templates = $this->listTemplates();
        } catch (WhatsAppApiException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'تعذّر الاتصال بـFlaxxa: '.$e->getMessage()];
        }

        $approved = count($templates);

        return [
            'success' => true,
            'message' => "تم الاتصال بنجاح — {$approved} قالباً معتمداً.",
        ];
    }

    /**
     * Approved templates only. A pending or rejected template cannot be sent, so
     * offering it in the settings screen would only produce a runtime failure.
     *
     * @return array<int, array{name: string, language: string, category: string}>
     */
    public function listTemplates(): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::timeout($this->timeout)
                ->get($this->url('/api/v1/getTemplates'), ['token' => $this->token]);
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر الاتصال بـFlaxxa: '.$e->getMessage());
        }

        $body = $this->assertOk($response, 'templates');
        $templates = $body['templates'] ?? [];

        if (! is_array($templates)) {
            return [];
        }

        return array_values(array_map(
            fn (array $template): array => [
                'name' => (string) ($template['name'] ?? ''),
                'language' => (string) ($template['language'] ?? ''),
                'category' => (string) ($template['category'] ?? ''),
            ],
            array_filter(
                $templates,
                fn ($template): bool => is_array($template)
                    && strtoupper((string) ($template['status'] ?? '')) === 'APPROVED'
                    && ($template['name'] ?? '') !== ''
            )
        ));
    }

    /**
     * Delivery state for a message, by Flaxxa's own numeric id (not the wamid).
     *
     * @return array{value: ?string, error: ?string}
     */
    public function fetchStatus(int $flaxxaMessageId): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::timeout($this->timeout)->asJson()->post(
                $this->url('/api/v1/get_message_response'),
                ['token' => $this->token, 'message_id' => $flaxxaMessageId],
            );
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر الاتصال بـFlaxxa: '.$e->getMessage());
        }

        $body = $this->assertOk($response, 'status');

        return [
            'value' => isset($body['data']['value']) ? (string) $body['data']['value'] : null,
            'error' => isset($body['data']['error_details']) ? (string) $body['data']['error_details'] : null,
        ];
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postJson(string $path, array $payload, string $type): SendMessageResponseDTO
    {
        $this->ensureConfigured();

        try {
            // On JSON endpoints the token belongs in the body, not a header.
            $response = Http::timeout($this->timeout)
                ->asJson()
                ->post($this->url($path), ['token' => $this->token, ...$payload]);
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر الاتصال بـFlaxxa: '.$e->getMessage(), 0, null, [
                'type' => $type,
            ]);
        }

        return $this->toDto($this->assertOk($response, $type, $payload['phone'] ?? null));
    }

    /**
     * The single gate every response passes through.
     *
     * Checks the HTTP status **and** the body's `status` field, because Flaxxa can
     * answer 200 with `{"status":"error"}` — treating that as success would mark
     * undelivered messages as sent and hide the reason entirely.
     *
     * @return array<string, mixed>
     */
    protected function assertOk(Response $response, string $type, ?string $recipient = null): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $status = strtolower((string) ($body['status'] ?? ''));
        $isError = $response->failed() || $status === 'error' || $status === 'failed';

        if (! $isError) {
            return $body;
        }

        $message = $this->errorMessage($response, $body);

        Log::channel('whatsapp')->error('Flaxxa send failed', [
            'status' => $response->status(),
            'body_status' => $body['status'] ?? null,
            'message' => $message,
            'type' => $type,
            // Recipient is logged; the message body deliberately is not.
            'to' => $recipient,
        ]);

        throw new WhatsAppApiException($message, $response->status(), null, [
            'status' => $response->status(),
            'body_status' => $body['status'] ?? null,
            'type' => $type,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function errorMessage(Response $response, array $body): string
    {
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        // Laravel validation shape: {"errors": {"field": ["..."]}}
        if (isset($body['errors']) && is_array($body['errors'])) {
            $first = collect($body['errors'])->flatten()->first();

            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        if ($response->status() === 429) {
            return 'تجاوزت حدّ Flaxxa (60 طلباً في الدقيقة). أعد المحاولة بعد قليل.';
        }

        return 'فشل طلب Flaxxa برمز '.$response->status();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function toDto(array $body): SendMessageResponseDTO
    {
        // The wamid is Meta's own id, so it lands in the same column as a direct Meta
        // send. Flaxxa's numeric `message_id` is kept in the raw response — the status
        // poller needs it, and only Flaxxa understands it.
        $wamid = $body['message_wamid'] ?? null;

        if (! is_string($wamid) || $wamid === '') {
            throw new WhatsAppApiException('استجابة Flaxxa لا تحتوي على معرّف رسالة.', 0, null, [
                'response_keys' => array_keys($body),
            ]);
        }

        return new SendMessageResponseDTO($wamid, $body);
    }

    protected function downloadAttachment(string $url): string
    {
        $maxBytes = (int) config('whatsapp.flaxxa.max_attachment_bytes', 16 * 1024 * 1024);

        try {
            $response = Http::timeout((int) config('whatsapp.flaxxa.attachment_timeout', 60))->get($url);
        } catch (\Throwable $e) {
            throw new WhatsAppApiException('تعذّر تنزيل المرفق: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new WhatsAppApiException('تعذّر تنزيل المرفق — استجاب الرابط بالرمز '.$response->status());
        }

        $contents = $response->body();

        if (strlen($contents) > $maxBytes) {
            throw new WhatsAppApiException(sprintf(
                'حجم المرفق %s ويتجاوز الحد المسموح %s.',
                $this->humanBytes(strlen($contents)),
                $this->humanBytes($maxBytes),
            ));
        }

        if ($contents === '') {
            throw new WhatsAppApiException('المرفق فارغ — تحقّق من الرابط.');
        }

        return $contents;
    }

    protected function humanBytes(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 2).' MB';
    }

    protected function recipient(string $to): string
    {
        // Flaxxa documents E.164 with an optional leading '+', which is exactly what the
        // shared normaliser's default branch produces.
        return WhatsAppRecipientNormalizer::normalize('flaxxa', $to);
    }

    protected function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new WhatsAppApiException('إعدادات Flaxxa غير مكتملة — يلزم رابط الخدمة ورمز الوصول.');
        }
    }
}
