<?php

namespace App\Services\WhatsApp;

use App\Models\EvolutionInstance;
use App\Models\WhatsAppMessage;
use App\Support\Dashboard\ContributesToDashboard;

/**
 * What the WhatsApp module shows on the dashboard.
 *
 * These figures used to be queried inside DashboardStats, which meant the core's home
 * screen imported this module's models and could not render without them.
 */
class WhatsAppDashboardContributor implements ContributesToDashboard
{
    public function statCards(): array
    {
        return [[
            'key' => 'messages',
            'title' => 'الرسائل',
            'value' => $this->safeCount(fn (): int => WhatsAppMessage::query()
                ->where('direction', WhatsAppMessage::DIRECTION_INBOUND)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count()),
            'subtext' => 'رسائل واردة هذا الشهر',
            'theme' => 'blue',
            'icon' => 'ri-mail-line',
            'route' => 'admin.whatsapp-messages.index',
        ]];
    }

    public function healthChecks(): array
    {
        return [[
            'label' => 'أرقام واتساب متصلة',
            // Passes when no instance has been added at all: a project that installed
            // the module but does not use it is not an unhealthy project.
            'passed' => $this->safeCount(fn (): int => EvolutionInstance::query()->count()) === 0
                || $this->safeCount(fn (): int => EvolutionInstance::query()->connected()->count()) > 0,
            'failure' => 'جميع أرقام واتساب مقطوعة',
        ]];
    }

    public function todayMetrics(): array
    {
        return [[
            'label' => 'رسائل واردة',
            'value' => $this->safeCount(fn (): int => WhatsAppMessage::query()
                ->where('direction', WhatsAppMessage::DIRECTION_INBOUND)
                ->whereDate('created_at', today())
                ->count()),
            'icon' => 'ri-mail-open-line',
            'color' => 'blue',
        ]];
    }

    /**
     * A missing table on a partially migrated install reports zero rather than taking
     * the whole dashboard down — the same guarantee DashboardStats gives its own counts.
     *
     * @param  \Closure(): int  $query
     */
    private function safeCount(\Closure $query): int
    {
        try {
            return $query();
        } catch (\Throwable) {
            return 0;
        }
    }
}
