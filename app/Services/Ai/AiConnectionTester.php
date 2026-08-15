<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use RuntimeException;
use Throwable;

use function Laravel\Ai\agent;

class AiConnectionTester
{
    /**
     * @param  array{name?: string, driver: string, api_key?: ?string, base_url?: ?string, default_model?: ?string, options?: ?array}  $payload
     * @return array{ok: bool, message: string, sample?: string}
     */
    public function probe(array $payload): array
    {
        $temp = new AiProvider([
            'name' => $payload['name'] ?? 'اختبار',
            'driver' => $payload['driver'],
            'api_key' => $payload['api_key'] ?? null,
            'base_url' => $payload['base_url'] ?? null,
            'default_model' => $payload['default_model'] ?? null,
            'options' => $payload['options'] ?? null,
            'is_active' => true,
            'is_default' => false,
        ]);

        if ($temp->requiresApiKey() && ! filled($temp->api_key)) {
            return [
                'ok' => false,
                'message' => 'مفتاح API مطلوب لاختبار هذا المزود.',
            ];
        }

        if ($temp->requiresBaseUrl() && ! filled($temp->base_url)) {
            return [
                'ok' => false,
                'message' => 'عنوان Base URL مطلوب لاختبار هذا المزود.',
            ];
        }

        $configName = 'ai_probe_'.uniqid();
        AiProviderConfigApplier::applyTemporary($configName, $temp->toAiProviderConfig());

        try {
            $response = agent(
                instructions: 'You are a connection probe. Reply with exactly the word ok and nothing else.',
            )->prompt(
                'ping',
                provider: $configName,
                model: filled($temp->default_model) ? $temp->default_model : null,
                timeout: 45,
            );

            $text = trim((string) ($response->text ?? ''));

            if ($text === '') {
                throw new RuntimeException('المزود لم يُرجع أي نص.');
            }

            return [
                'ok' => true,
                'message' => 'تم الاتصال بالمزود بنجاح.',
                'sample' => mb_substr($text, 0, 120),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'فشل اختبار الاتصال: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string, sample?: string}
     */
    public function probeProvider(AiProvider $provider, ?string $apiKeyOverride = null): array
    {
        return $this->probe([
            'name' => $provider->name,
            'driver' => $provider->driver,
            'api_key' => filled($apiKeyOverride) ? $apiKeyOverride : $provider->api_key,
            'base_url' => $provider->base_url,
            'default_model' => $provider->default_model,
            'options' => $provider->options,
        ]);
    }
}
