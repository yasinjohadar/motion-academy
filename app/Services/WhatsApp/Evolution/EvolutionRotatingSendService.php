<?php

namespace App\Services\WhatsApp\Evolution;

use App\Exceptions\WhatsAppApiException;
use App\Models\EvolutionInstance;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EvolutionRotatingSendService
{
    private const GLOBAL_SEND_CACHE_KEY = 'evolution_global_last_send';

    private const GLOBAL_SEND_LOCK_KEY = 'evolution_global_send_delay_lock';

    public function __construct(
        private WhatsAppSettingsService $settingsService,
        private EvolutionInstanceRotator $rotator,
        private EvolutionService $evolutionService,
    ) {}

    public function isRotationActive(): bool
    {
        $settings = $this->settingsService->getSettings();

        if (($settings['whatsapp_provider'] ?? '') !== 'evolution') {
            return false;
        }

        return filter_var($settings['evolution_rotation_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @template T
     *
     * @param  callable(string $instanceName): T  $sendFn
     * @return array{result: T, instance_name: string}
     */
    public function sendWithRotation(callable $sendFn, ?string $forcedInstanceName = null, bool $applyDelay = true): array
    {
        if ($applyDelay) {
            $this->waitBeforeNextGlobalSend();
        }

        if ($forcedInstanceName !== null && $forcedInstanceName !== '') {
            if ($applyDelay) {
                $this->waitBeforeSend($forcedInstanceName);
            }

            $result = $sendFn($forcedInstanceName);

            return [
                'result' => $result,
                'instance_name' => $forcedInstanceName,
            ];
        }

        if (! $this->isRotationActive()) {
            $instanceName = $this->fallbackInstanceName();
            if ($instanceName === '') {
                throw new EvolutionApiException(
                    'لم يُحدَّد Instance افتراضي لـ Evolution API. راجع الإعدادات.',
                    'No default Evolution instance configured.',
                );
            }

            if ($applyDelay) {
                $this->waitBeforeSend($instanceName);
            }

            $result = $sendFn($instanceName);

            return [
                'result' => $result,
                'instance_name' => $instanceName,
            ];
        }

        $this->evolutionService->refreshRotationCandidates();
        $pool = $this->rotator->orderedPoolForFailover(true);

        if ($pool->isEmpty()) {
            throw new EvolutionApiException(
                'لا توجد أرقام متصلة ومفعّلة للتبديل. اربط رقماً إضافياً (open) وفعّل التبديل من صفحة Evolution Instances.',
                'Rotation pool is empty.',
            );
        }

        $lastException = null;

        foreach ($pool as $instance) {
            try {
                if ($applyDelay) {
                    $this->waitBeforeSend($instance->instance_name);
                }

                $result = $sendFn($instance->instance_name);
                $this->rotator->markUsed($instance);

                Log::channel('whatsapp')->info('Evolution rotation send succeeded', [
                    'instance' => $instance->instance_name,
                    'phone' => $instance->phone_number,
                ]);

                return [
                    'result' => $result,
                    'instance_name' => $instance->instance_name,
                ];
            } catch (Throwable $e) {
                $lastException = $e;
                Log::channel('whatsapp')->warning('Evolution rotation send failed, trying next instance', [
                    'instance' => $instance->instance_name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($lastException instanceof EvolutionApiException) {
            throw $lastException;
        }

        if ($lastException instanceof WhatsAppApiException) {
            throw $lastException;
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new EvolutionApiException(
            'فشل الإرسال عبر جميع أرقام التبديل المتاحة ('.$pool->count().'). راجع حالة الاتصال في Evolution Instances.',
            'All rotation-eligible Evolution instances failed.',
        );
    }

    public function fallbackInstanceName(): string
    {
        $settings = $this->settingsService->getSettings();
        $configured = trim((string) ($settings['evolution_instance_name'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        return EvolutionInstance::defaultInstance()?->instance_name ?? '';
    }

    /**
     * Enforce minimum gap between any Evolution send (all instances).
     */
    public function waitBeforeNextGlobalSend(): void
    {
        $delaySeconds = $this->settingsService->calculateDelay();
        if ($delaySeconds <= 0) {
            return;
        }

        Cache::lock(self::GLOBAL_SEND_LOCK_KEY, 30)->block(15, function () use ($delaySeconds): void {
            $lastSentAt = Cache::get(self::GLOBAL_SEND_CACHE_KEY);
            if (is_numeric($lastSentAt)) {
                $waitSeconds = $delaySeconds - (microtime(true) - (float) $lastSentAt);
                if ($waitSeconds > 0) {
                    usleep((int) round($waitSeconds * 1_000_000));
                }
            }

            Cache::put(self::GLOBAL_SEND_CACHE_KEY, microtime(true), now()->addHours(2));
        });
    }

    /**
     * Enforce minimum gap between sends per instance (reduces ban risk).
     */
    public function waitBeforeSend(string $instanceName): void
    {
        $delaySeconds = $this->settingsService->calculateDelay();
        if ($delaySeconds <= 0) {
            return;
        }

        $cacheKey = 'evolution_instance_last_send:'.md5($instanceName);
        $lockKey = 'evolution_send_delay_lock:'.md5($instanceName);

        Cache::lock($lockKey, 30)->block(15, function () use ($cacheKey, $delaySeconds): void {
            $lastSentAt = Cache::get($cacheKey);
            if (is_numeric($lastSentAt)) {
                $waitSeconds = $delaySeconds - (microtime(true) - (float) $lastSentAt);
                if ($waitSeconds > 0) {
                    usleep((int) round($waitSeconds * 1_000_000));
                }
            }

            Cache::put($cacheKey, microtime(true), now()->addHours(2));
        });
    }
}
