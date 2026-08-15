<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail for a model, driven entirely by `config/observability.php`.
 *
 * Centralising the field whitelist means the redaction guarantee lives in one
 * reviewable place instead of nine `getActivitylogOptions()` methods that can drift.
 *
 * The `never_log` deny-list is applied on top of every whitelist as defence in depth:
 * Spatie logs *attribute values*, so an `encrypted` cast (AiProvider::api_key,
 * SmtpSetting::password, EvolutionInstance::evolution_api_key) would otherwise write
 * the decrypted secret straight into activity_log.properties.
 *
 * Descriptions are deliberately left as Spatie's machine values (created/updated/
 * deleted) rather than Arabic strings — the UI translates them, so the log stays
 * language-neutral.
 */
trait AuditsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditedFields())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->auditLogName());
    }

    /**
     * @return array<int, string>
     */
    public function auditedFields(): array
    {
        $configured = config('observability.audit.models')[static::class]['fields'] ?? [];
        $denied = config('observability.audit.never_log', []);

        return array_values(array_diff($configured, $denied));
    }

    public function auditLogName(): string
    {
        return config('observability.audit.models')[static::class]['log_name'] ?? 'default';
    }
}
