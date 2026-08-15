<?php

namespace App\Support\Dashboard;

/**
 * Lets a module add its own numbers to the dashboard.
 *
 * DashboardStats used to query WhatsAppMessage and EvolutionInstance directly, so the
 * core's home screen could not render without that module's tables. A module knows
 * what is worth showing about itself; the core only knows how to lay it out.
 *
 * Implementations are listed in `config('dashboard.contributors')`. Contributions are
 * appended after the core's own, and every method may return an empty array.
 */
interface ContributesToDashboard
{
    /**
     * Large tiles in the overview row.
     *
     * Shape: key, title, value, subtext, theme, icon, and an optional `route` name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function statCards(): array;

    /**
     * Checks folded into the system-health score.
     *
     * A check must pass when the module is installed but unused — an empty WhatsApp
     * setup is not an unhealthy system, and marking it so would train operators to
     * ignore the score.
     *
     * @return array<int, array{label: string, passed: bool, failure: string}>
     */
    public function healthChecks(): array;

    /**
     * Small "today" figures under the overview.
     *
     * Shape: label, value, icon, color.
     *
     * @return array<int, array<string, mixed>>
     */
    public function todayMetrics(): array;
}
