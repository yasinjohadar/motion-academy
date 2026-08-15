<?php

namespace App\Console\Commands;

use App\Services\Observability\SecurityLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Prunes the observability tables with a different retention per log_name.
 *
 * Spatie ships `activitylog:clean`, but it deletes by one global age — which would
 * throw away the security timeline on the same schedule as routine setting edits.
 * Security events are the only record available when investigating an incident, so
 * they are kept considerably longer.
 */
class PruneObservabilityCommand extends Command
{
    protected $signature = 'observability:prune
                            {--security-days= : أيام الاحتفاظ بالسجل الأمني}
                            {--default-days= : أيام الاحتفاظ بباقي سجل التدقيق}
                            {--webhook-days= : أيام الاحتفاظ بأحداث webhook المعالجة}
                            {--dry-run : عرض ما سيُحذف دون تنفيذ}';

    protected $description = 'حذف السجلات القديمة حسب مدد الاحتفاظ في config/observability.php';

    public function handle(): int
    {
        $securityDays = (int) ($this->option('security-days') ?? config('observability.retention.security', 365));
        $defaultDays = (int) ($this->option('default-days') ?? config('observability.retention.default', 180));
        $webhookDays = (int) ($this->option('webhook-days') ?? config('observability.retention.webhook_events', 30));
        $dryRun = (bool) $this->option('dry-run');

        $securityQuery = fn () => Activity::query()
            ->where('log_name', SecurityLogger::LOG_NAME)
            ->where('created_at', '<', now()->subDays($securityDays));

        $auditQuery = fn () => Activity::query()
            ->where('log_name', '!=', SecurityLogger::LOG_NAME)
            ->where('created_at', '<', now()->subDays($defaultDays));

        $webhookQuery = fn () => DB::table('whatsapp_webhook_events')
            ->whereNotNull('processed_at')
            ->where('created_at', '<', now()->subDays($webhookDays));

        $security = $securityDays > 0 ? $this->purge($securityQuery, $dryRun) : 0;
        $audit = $defaultDays > 0 ? $this->purge($auditQuery, $dryRun) : 0;
        $webhooks = $webhookDays > 0 ? $this->purge($webhookQuery, $dryRun) : 0;

        $verb = $dryRun ? 'سيتم حذف' : 'تم حذف';

        $this->info("{$verb}: {$audit} سجل تدقيق، {$security} سجل أمني، {$webhooks} حدث webhook.");

        return self::SUCCESS;
    }

    /**
     * @param  \Closure():(Builder|\Illuminate\Database\Query\Builder)  $query
     */
    protected function purge(\Closure $query, bool $dryRun): int
    {
        try {
            return $dryRun ? $query()->count() : $query()->delete();
        } catch (\Throwable $e) {
            // A table may not exist yet on a partially-migrated install.
            $this->warn('تعذّر التقليم: '.$e->getMessage());

            return 0;
        }
    }
}
