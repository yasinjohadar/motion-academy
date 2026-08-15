<?php

namespace App\Services\Dashboard;

use App\Models\AppNotification;
use App\Models\EvolutionInstance;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\Observability\SecurityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Real numbers for the admin dashboard.
 *
 * Every value here is queried, never hard-coded. Each query is a counted lookup on an
 * indexed column, so the dashboard stays cheap without caching; if the tables grow
 * large enough for that to change, wrap overview() in a short Cache::remember().
 */
class DashboardStats
{
    /**
     * The four headline tiles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        $health = $this->systemHealth();

        return [
            [
                'key' => 'users',
                'title' => 'المستخدمون',
                'value' => $this->count(fn () => User::query()->count()),
                'subtext' => 'إجمالي الحسابات',
                'theme' => 'purple',
                'icon' => 'ri-user-line',
                'route' => 'admin.users.index',
            ],
            [
                'key' => 'logins',
                'title' => 'تسجيلات الدخول',
                'value' => $this->count(fn () => Activity::query()
                    ->where('log_name', SecurityLogger::LOG_NAME)
                    ->where('event', 'login')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count()),
                'subtext' => 'خلال هذا الشهر',
                'theme' => 'green',
                'icon' => 'ri-login-circle-line',
                'route' => 'admin.observability.security',
            ],
            [
                'key' => 'messages',
                'title' => 'الرسائل',
                'value' => $this->count(fn () => WhatsAppMessage::query()
                    ->where('direction', WhatsAppMessage::DIRECTION_INBOUND)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count()),
                'subtext' => 'رسائل واردة هذا الشهر',
                'theme' => 'blue',
                'icon' => 'ri-mail-line',
                'route' => 'admin.whatsapp-messages.index',
            ],
            [
                'key' => 'health',
                'title' => 'حالة النظام',
                'value' => $health['score'],
                'subtext' => $health['summary'],
                'theme' => $health['score'] === 100 ? 'orange' : 'red',
                'icon' => $health['score'] === 100 ? 'ri-shield-check-line' : 'ri-shield-flash-line',
                'route' => 'admin.observability.index',
            ],
        ];
    }

    /**
     * Health as the share of passing checks, plus what is actually wrong.
     *
     * @return array{score: int, summary: string, checks: array<int, array<string, mixed>>}
     */
    public function systemHealth(): array
    {
        $checks = [
            [
                'label' => 'لا توجد مهام فاشلة',
                'passed' => $this->count(fn () => DB::table('failed_jobs')->count()) === 0,
                'failure' => 'توجد مهام فاشلة',
            ],
            [
                'label' => 'قائمة الانتظار غير متكدّسة',
                'passed' => $this->count(fn () => DB::table('jobs')->count()) < 100,
                'failure' => 'تكدّس في قائمة الانتظار',
            ],
            [
                'label' => 'لا أحداث webhook معلّقة',
                'passed' => $this->count(fn () => DB::table('whatsapp_webhook_events')
                    ->whereNull('processed_at')->count()) === 0,
                'failure' => 'أحداث webhook غير معالجة',
            ],
            [
                'label' => 'إعداد بريد نشِط',
                'passed' => $this->count(fn () => SmtpSetting::query()->where('is_active', true)->count()) > 0,
                'failure' => 'لا يوجد إعداد SMTP نشِط',
            ],
            [
                'label' => 'أرقام واتساب متصلة',
                // Only meaningful once at least one instance has been added.
                'passed' => $this->count(fn () => EvolutionInstance::query()->count()) === 0
                    || $this->count(fn () => EvolutionInstance::query()->connected()->count()) > 0,
                'failure' => 'جميع أرقام واتساب مقطوعة',
            ],
        ];

        $failed = array_values(array_filter($checks, fn (array $check): bool => ! $check['passed']));
        $score = (int) round(((count($checks) - count($failed)) / count($checks)) * 100);

        return [
            'score' => $score,
            'summary' => $failed === []
                ? 'يعمل بشكل طبيعي'
                : ($failed[0]['failure'].(count($failed) > 1 ? ' (+'.(count($failed) - 1).')' : '')),
            'checks' => array_map(fn (array $check): array => [
                'label' => $check['label'],
                'passed' => $check['passed'],
            ], $checks),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function todaySummary(User $user): array
    {
        return [
            [
                'label' => 'تسجيلات اليوم',
                'value' => $this->count(fn () => User::query()->whereDate('created_at', today())->count()),
                'icon' => 'ri-user-add-line',
                'color' => 'green',
            ],
            [
                'label' => 'رسائل واردة',
                'value' => $this->count(fn () => WhatsAppMessage::query()
                    ->where('direction', WhatsAppMessage::DIRECTION_INBOUND)
                    ->whereDate('created_at', today())
                    ->count()),
                'icon' => 'ri-mail-open-line',
                'color' => 'blue',
            ],
            [
                'label' => 'مهام معلقة',
                'value' => $this->count(fn () => DB::table('jobs')->count()),
                'icon' => 'ri-task-line',
                'color' => 'orange',
            ],
            [
                'label' => 'إشعارات غير مقروءة',
                'value' => $this->count(fn () => AppNotification::query()
                    ->forUser($user)->whereNull('read_at')->count()),
                'icon' => 'ri-notification-3-line',
                'color' => 'purple',
            ],
        ];
    }

    /**
     * Daily activity counts for the bar chart. Always returns one entry per day —
     * including zero days, so the chart keeps an honest time axis.
     *
     * @return array<int, array{date: string, label: string, total: int}>
     */
    public function activityChart(int $days = 14): array
    {
        $from = today()->subDays($days - 1);

        $counts = $this->safely(fn () => Activity::query()
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day'), collect());

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($from, $counts): array {
                $date = $from->copy()->addDays($offset);
                $key = $date->format('Y-m-d');

                return [
                    'date' => $key,
                    'label' => $date->format('d/m'),
                    'total' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivity(int $limit = 6): array
    {
        return $this->safely(fn () => Activity::query()
            ->with('causer:id,name,email')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_label' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'causer_name' => $activity->causer?->name,
                'causer_email' => $activity->causer?->email,
                'created_at' => $activity->created_at?->format('Y-m-d H:i'),
                'created_at_human' => $activity->created_at?->diffForHumans(),
            ])
            ->all(), []);
    }

    public function lastLoginAt(User $user): ?Carbon
    {
        return $user->last_login_at;
    }

    /**
     * Counts must never take the dashboard down — a missing table on a partially
     * migrated install reports zero rather than a 500.
     */
    protected function count(\Closure $query): int
    {
        return (int) $this->safely($query, 0);
    }

    protected function safely(\Closure $query, mixed $fallback): mixed
    {
        try {
            return $query();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
