<?php

namespace App\Services\WhatsApp\Evolution;

use App\Models\EvolutionInstance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EvolutionInstanceRotator
{
    private const REFRESH_CACHE_KEY = 'evolution_rotation_pool_refreshed_at';

    public function __construct(
        private EvolutionService $evolutionService,
    ) {}

    public function pool(bool $forceRefresh = false): Collection
    {
        return $this->rotationOrderedPool($forceRefresh);
    }

    public function poolCount(bool $forceRefresh = false): int
    {
        return $this->pool($forceRefresh)->count();
    }

    public function nextInstance(bool $forceRefresh = false): EvolutionInstance
    {
        $pool = $this->pool($forceRefresh);

        if ($pool->isEmpty()) {
            throw new EvolutionApiException(
                'لا توجد أرقام WhatsApp متصلة ومفعّلة للتبديل. اربط instance واحداً على الأقل من لوحة Evolution.',
                'No rotation-eligible Evolution instances available.',
            );
        }

        return $pool->first();
    }

    /**
     * Pool ordered for failover: least-recently-used first, then next candidates.
     */
    public function orderedPoolForFailover(bool $forceRefresh = false): Collection
    {
        return $this->rotationOrderedPool($forceRefresh);
    }

    public function markUsed(EvolutionInstance $instance): void
    {
        $instance->update(['last_used_at' => now()]);
    }

    private function rotationOrderedPool(bool $forceRefresh): Collection
    {
        $this->refreshPoolStatusesIfDue($forceRefresh);

        return EvolutionInstance::rotationEligible()
            ->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at')
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function refreshPoolStatusesIfDue(bool $forceRefresh): void
    {
        if (! $forceRefresh && Cache::get(self::REFRESH_CACHE_KEY)) {
            return;
        }

        $this->evolutionService->refreshRotationCandidates();
        Cache::put(self::REFRESH_CACHE_KEY, true, now()->addSeconds(60));
    }
}
