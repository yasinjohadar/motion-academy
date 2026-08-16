<?php

namespace App\Modules\WhatsApp\Services\Evolution;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiClient
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->apiKey = $apiKey;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            $config['base_url'] ?? '',
            $config['api_key'] ?? ''
        );
    }

    public function getInformation(): array
    {
        return $this->request('GET', '/');
    }

    public function fetchInstances(?string $instanceName = null): array
    {
        $path = '/instance/fetchInstances';
        if ($instanceName) {
            $path .= '?instanceName='.urlencode($instanceName);
        }

        return $this->request('GET', $path);
    }

    public function getConnectionState(string $instanceName): array
    {
        return $this->request('GET', $this->instancePath('/instance/connectionState', $instanceName));
    }

    public function connectInstance(string $instanceName, ?string $phoneNumber = null): array
    {
        $path = $this->instancePath('/instance/connect', $instanceName);
        if ($phoneNumber) {
            $path .= '?number='.urlencode($phoneNumber);
        }

        return $this->request('GET', $path);
    }

    public function createInstance(array $params): array
    {
        return $this->request('POST', '/instance/create', $params);
    }

    public function restartInstance(string $instanceName): array
    {
        return $this->request('POST', $this->instancePath('/instance/restart', $instanceName));
    }

    public function logoutInstance(string $instanceName): array
    {
        return $this->request('DELETE', $this->instancePath('/instance/logout', $instanceName));
    }

    public function deleteInstance(string $instanceName): array
    {
        return $this->request('DELETE', $this->instancePath('/instance/delete', $instanceName));
    }

    public function setPresence(string $instanceName, string $presence): array
    {
        return $this->request('POST', $this->instancePath('/instance/setPresence', $instanceName), [
            'presence' => $presence,
        ]);
    }

    public function sendChatPresence(string $instanceName, string $number, string $presence, int $delayMs = 3000): array
    {
        return $this->request('POST', $this->instancePath('/chat/sendPresence', $instanceName), [
            'number' => $number,
            'presence' => $presence,
            'delay' => $delayMs,
        ]);
    }

    public function getWebhook(string $instanceName): array
    {
        return $this->request('GET', $this->instancePath('/webhook/find', $instanceName));
    }

    public function setWebhook(string $instanceName, array $config): array
    {
        // Evolution API v2 expects: { "webhook": { enabled, url, events, ... } }
        $webhook = isset($config['webhook']) && is_array($config['webhook'])
            ? $config['webhook']
            : $config;

        return $this->request('POST', $this->instancePath('/webhook/set', $instanceName), [
            'webhook' => $webhook,
        ]);
    }

    public function setSettings(string $instanceName, array $settings): array
    {
        $payload = isset($settings['settings']) && is_array($settings['settings'])
            ? $settings
            : ['settings' => $settings];

        return $this->request('POST', $this->instancePath('/settings/set', $instanceName), $payload);
    }

    public function getSettings(string $instanceName): array
    {
        return $this->request('GET', $this->instancePath('/settings/find', $instanceName));
    }

    public function sendText(string $instanceName, string $number, string $text, array $options = []): array
    {
        return $this->request('POST', $this->instancePath('/message/sendText', $instanceName), array_merge([
            'number' => $number,
            'text' => $text,
        ], $options));
    }

    public function sendMedia(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendMedia', $instanceName), $payload);
    }

    public function sendWhatsAppAudio(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendWhatsAppAudio', $instanceName), $payload);
    }

    public function sendSticker(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendSticker', $instanceName), $payload);
    }

    public function sendLocation(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendLocation', $instanceName), $payload);
    }

    public function sendContact(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendContact', $instanceName), $payload);
    }

    public function sendReaction(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendReaction', $instanceName), $payload);
    }

    public function sendPoll(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendPoll', $instanceName), $payload);
    }

    public function sendList(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendList', $instanceName), $payload);
    }

    public function sendButtons(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendButtons', $instanceName), $payload);
    }

    public function sendStatus(string $instanceName, array $payload): array
    {
        return $this->request('POST', $this->instancePath('/message/sendStatus', $instanceName), $payload);
    }

    public function fetchAllGroups(string $instanceName, bool $getParticipants = false): array
    {
        $path = $this->instancePath('/group/fetchAllGroups', $instanceName).'?getParticipants='.($getParticipants ? 'true' : 'false');

        return $this->request('GET', $path);
    }

    public function findGroupByJid(string $instanceName, string $groupJid): array
    {
        return $this->request('GET', $this->instancePath('/group/findGroupInfos', $instanceName).'?groupJid='.urlencode($groupJid));
    }

    public function findGroupMembers(string $instanceName, string $groupJid): array
    {
        return $this->request('GET', $this->instancePath('/group/participants', $instanceName).'?groupJid='.urlencode($groupJid));
    }

    public function findChats(string $instanceName): array
    {
        return $this->request('POST', $this->instancePath('/chat/findChats', $instanceName));
    }

    public function findContacts(string $instanceName): array
    {
        return $this->request('POST', $this->instancePath('/chat/findContacts', $instanceName));
    }

    /**
     * Check whether numbers are registered on WhatsApp.
     *
     * @param  list<string>  $numbers  Digits with country code (no +)
     * @return list<array{exists?: bool, jid?: string, number?: string}>
     */
    public function whatsappNumbers(string $instanceName, array $numbers): array
    {
        $response = $this->request('POST', $this->instancePath('/chat/whatsappNumbers', $instanceName), [
            'numbers' => array_values($numbers),
        ]);

        if (array_is_list($response)) {
            return $response;
        }

        $nested = $response['data'] ?? $response['numbers'] ?? null;

        return is_array($nested) ? array_values($nested) : [];
    }

    protected function instancePath(string $prefix, string $instanceName): string
    {
        return rtrim($prefix, '/').'/'.rawurlencode($instanceName);
    }

    protected function request(string $method, string $path, array $data = []): array
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new EvolutionApiException(
                'لم يتم إعداد Evolution API بعد. أدخل Base URL ومفتاح API في صفحة الإعدادات ثم احفظ.',
                'Evolution API URL and API Key are required.',
            );
        }

        $timeout = max(10, (int) config('whatsapp.timeout', 30));
        $connectTimeout = max(10, (int) config('whatsapp.evolution_connect_timeout', 30));

        $client = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withHeaders([
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]);

        try {
            /** @var Response $response */
            $response = match (strtoupper($method)) {
                'GET' => $client->get($this->baseUrl.$path),
                'POST' => $client->post($this->baseUrl.$path, $data),
                'DELETE' => $client->delete($this->baseUrl.$path, $data),
                'PUT' => $client->put($this->baseUrl.$path, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };
        } catch (ConnectionException $e) {
            $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: $this->baseUrl;

            Log::channel('whatsapp')->error('Evolution API connection failed', [
                'method' => $method,
                'path' => $path,
                'base_url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);

            throw new EvolutionApiException(
                'تعذر الاتصال بخادم Evolution API على «'.$host.'». تأكد أن الخدمة تعمل وأن عنوان Base URL في الإعدادات صحيح.',
                'Connection failed for '.$this->baseUrl.$path.': '.$e->getMessage(),
                0,
                $e,
            );
        }

        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : ['raw' => $response->body()];
        }

        $status = $response->status();
        $body = $response->body();
        $errorData = $response->json();
        $technicalMessage = $this->extractErrorMessage($errorData, $body);
        $userMessage = $this->friendlyHttpError($status, $technicalMessage, $body);

        Log::channel('whatsapp')->error('Evolution API error', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'error' => $errorData ?: $body,
        ]);

        throw new EvolutionApiException($userMessage, $technicalMessage, $status);
    }

    protected function friendlyHttpError(int $status, string $technicalMessage, string $body): string
    {
        if (EvolutionApiException::looksLikeHtml($body) || EvolutionApiException::looksLikeHtml($technicalMessage)) {
            return match (true) {
                $status === 404 => $this->notFoundMessage(),
                $status === 401, $status === 403 => 'مفتاح API (apikey) غير صحيح أو غير مصرح به. راجع الإعدادات.',
                $status >= 500 => 'خادم Evolution API يواجه مشكلة حالياً. حاول لاحقاً أو تواصل مع مزوّد الاستضافة.',
                default => 'تعذر الاتصال بـ Evolution API. راجع إعدادات الاتصال وحاول مرة أخرى.',
            };
        }

        if ($status === 404) {
            return $this->notFoundMessage();
        }

        if ($status === 401 || $status === 403) {
            return 'مفتاح API (apikey) غير صحيح أو غير مصرح به. راجع الإعدادات.';
        }

        if ($status >= 500) {
            return 'خادم Evolution API يواجه مشكلة حالياً. حاول لاحقاً.';
        }

        if ($technicalMessage !== '' && strlen($technicalMessage) <= 300) {
            return $technicalMessage;
        }

        return 'فشل الاتصال بـ Evolution API (رمز '.$status.').';
    }

    protected function notFoundMessage(): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: $this->baseUrl;

        return 'تعذر الوصول إلى Evolution API على «'.$host.'». تأكد من صحة Base URL في الإعدادات وأن الخدمة تعمل على هذا العنوان.';
    }

    protected function extractErrorMessage(mixed $errorData, string $fallbackBody): string
    {
        if (EvolutionApiException::looksLikeHtml($fallbackBody)) {
            return 'HTTP error response (HTML)';
        }

        if (! is_array($errorData)) {
            return $fallbackBody !== '' ? $fallbackBody : 'Evolution API request failed';
        }

        $message = $errorData['message'] ?? $errorData['error'] ?? null;
        if (is_string($message) && $message !== '' && ! EvolutionApiException::looksLikeHtml($message)) {
            return $message;
        }

        if (is_array($message)) {
            return json_encode($message, JSON_UNESCAPED_UNICODE) ?: 'Evolution API request failed';
        }

        $nested = data_get($errorData, 'response.message');
        if (is_array($nested)) {
            return json_encode($nested, JSON_UNESCAPED_UNICODE) ?: 'Evolution API request failed';
        }

        if (is_string($nested) && $nested !== '' && ! EvolutionApiException::looksLikeHtml($nested)) {
            return $nested;
        }

        return $fallbackBody !== '' && ! EvolutionApiException::looksLikeHtml($fallbackBody)
            ? $fallbackBody
            : 'Evolution API request failed';
    }
}
