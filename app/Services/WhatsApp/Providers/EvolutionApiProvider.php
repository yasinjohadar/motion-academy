<?php

namespace App\Services\WhatsApp\Providers;

use App\DTOs\WhatsApp\SendMessageResponseDTO;
use App\Exceptions\WhatsAppApiException;
use App\Services\WhatsApp\Evolution\EvolutionApiClient;
use App\Services\WhatsApp\WhatsAppProviderService;
use App\Support\WhatsAppRecipientNormalizer;
use Illuminate\Support\Facades\Log;

class EvolutionApiProvider implements WhatsAppProviderService
{
    private EvolutionApiClient $client;

    private string $instanceName;

    private string $baseUrl;

    public function __construct(array $config)
    {
        $this->client = EvolutionApiClient::fromConfig([
            'base_url' => $config['base_url'] ?? '',
            'api_key' => $config['api_key'] ?? '',
        ]);
        $this->instanceName = (string) ($config['instance_name'] ?? '');
        $this->baseUrl = (string) ($config['base_url'] ?? '');
    }

    /**
     * Evolution also needs a paired instance to actually deliver — that is a database
     * question, answered separately via ProviderCapabilities::requiresConnectedInstance.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function sendText(string $to, string $text, bool $previewUrl = false): SendMessageResponseDTO
    {
        return $this->sendPlainText($to, $text, [
            'linkPreview' => $previewUrl,
        ]);
    }

    public function sendTemplate(string $to, string $templateName, string $language = 'ar', array $components = []): SendMessageResponseDTO
    {
        $body = $this->extractTemplateBody($components) ?? $templateName;

        return $this->sendPlainText($to, $body);
    }

    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): SendMessageResponseDTO
    {
        try {
            $number = $this->normalizeRecipient($to);
            $response = $this->client->sendMedia($this->instanceName, [
                'number' => $number,
                'mediatype' => 'document',
                'media' => $documentUrl,
                'fileName' => $filename,
                'caption' => $caption,
            ]);

            return $this->toResponseDto($response);
        } catch (\Throwable $e) {
            throw $this->wrapException($e);
        }
    }

    public function testConnection(): array
    {
        try {
            if ($this->instanceName === '') {
                return ['success' => false, 'message' => 'اسم Instance مطلوب.'];
            }

            $info = $this->client->getInformation();
            $state = $this->client->getConnectionState($this->instanceName);
            $isOpen = ($state['instance']['state'] ?? $state['state'] ?? '') === 'open';

            return [
                'success' => true,
                'message' => $isOpen
                    ? 'Evolution API متصل — Instance «'.$this->instanceName.'» نشط'
                    : 'Evolution API يعمل لكن Instance غير متصل. اربط الجهاز من لوحة Evolution.',
                'data' => [
                    'version' => $info['version'] ?? null,
                    'connection' => $state,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'فشل الاتصال: '.$e->getMessage(),
            ];
        }
    }

    public function sendPlainText(string $to, string $text, array $options = []): SendMessageResponseDTO
    {
        try {
            $number = $this->normalizeRecipient($to);
            $response = $this->client->sendText($this->instanceName, $number, $text, $options);

            return $this->toResponseDto($response);
        } catch (\Throwable $e) {
            throw $this->wrapException($e);
        }
    }

    public function sendMediaMessage(array $payload): SendMessageResponseDTO
    {
        try {
            $payload['number'] = $this->normalizeRecipient((string) ($payload['number'] ?? ''));
            $response = $this->client->sendMedia($this->instanceName, $payload);

            return $this->toResponseDto($response);
        } catch (\Throwable $e) {
            throw $this->wrapException($e);
        }
    }

    public function getClient(): EvolutionApiClient
    {
        return $this->client;
    }

    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    protected function normalizeRecipient(string $recipient): string
    {
        return WhatsAppRecipientNormalizer::normalizeForEvolution($recipient);
    }

    protected function extractTemplateBody(array $components): ?string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            foreach ($component['parameters'] ?? [] as $param) {
                if (($param['type'] ?? '') === 'text' && ! empty($param['text'])) {
                    return $param['text'];
                }
            }
        }

        return null;
    }

    protected function toResponseDto(array $response): SendMessageResponseDTO
    {
        $messageId = (string) (
            data_get($response, 'key.id')
            ?? data_get($response, 'messageId')
            ?? data_get($response, 'id')
            ?? ''
        );

        if ($messageId === '') {
            throw new WhatsAppApiException(
                'Evolution API: لم يُرجع معرف رسالة بعد الإرسال.',
                0
            );
        }

        Log::channel('whatsapp')->info('Evolution message sent', [
            'instance' => $this->instanceName,
            'message_id' => $messageId,
            'status' => data_get($response, 'status') ?? data_get($response, 'message.status'),
        ]);

        return new SendMessageResponseDTO(
            metaMessageId: $messageId,
            rawResponse: $response
        );
    }

    protected function wrapException(\Throwable $e): WhatsAppApiException
    {
        return new WhatsAppApiException(
            'Evolution API: '.$e->getMessage(),
            (int) $e->getCode(),
            $e instanceof \Exception ? $e : null
        );
    }
}
