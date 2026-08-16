<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Datetime casts are invisible to static analysis without this, so every
 * `->toDateTimeString()` on them reads as a call on a string.
 *
 * @property CarbonInterface|null $last_run_at
 * @property CarbonInterface|null $next_run_at
 */
class BackupSchedule extends Model
{
    use AuditsChanges, ExposesPolicyFlags;

    protected $fillable = [
        'name',
        'backup_type',
        'storage_config_id',
        'frequency',
        'time',
        'day_of_week',
        'day_of_month',
        'retention_days',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'retention_days' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function storageConfig(): BelongsTo
    {
        return $this->belongsTo(AppStorageConfig::class, 'storage_config_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculateNextRunAt(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();
        [$hour, $minute] = array_map('intval', explode(':', $this->time ?: '02:00'));

        $candidate = $from->copy()->setTime($hour, $minute, 0);

        return match ($this->frequency) {
            'weekly' => $this->nextWeekly($candidate, $from),
            'monthly' => $this->nextMonthly($candidate, $from),
            default => $candidate->greaterThan($from) ? $candidate : $candidate->addDay(),
        };
    }

    private function nextWeekly(Carbon $candidate, Carbon $from): Carbon
    {
        $targetDow = $this->day_of_week ?? 0;
        while ((int) $candidate->dayOfWeek !== $targetDow || $candidate->lessThanOrEqualTo($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextMonthly(Carbon $candidate, Carbon $from): Carbon
    {
        $day = min(max((int) ($this->day_of_month ?? 1), 1), 28);
        $candidate->day($day)->setTimeFromTimeString($this->time.':00');

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate->addMonthNoOverflow()->day($day)->setTimeFromTimeString($this->time.':00');
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'can' => $this->policyFlags(),
            'id' => $this->id,
            'name' => $this->name,
            'backup_type' => $this->backup_type,
            'backup_type_label' => match ($this->backup_type) {
                'database' => 'قاعدة البيانات',
                'files' => 'الملفات',
                'full' => 'كاملة',
                default => $this->backup_type,
            },
            'storage_config_id' => $this->storage_config_id,
            'storage_config' => $this->storageConfig
                ? [
                    'id' => $this->storageConfig->id,
                    'name' => $this->storageConfig->name,
                    'driver_label' => config("storage.drivers.{$this->storageConfig->driver}", $this->storageConfig->driver),
                ]
                : null,
            'frequency' => $this->frequency,
            'frequency_label' => match ($this->frequency) {
                'weekly' => 'أسبوعي',
                'monthly' => 'شهري',
                default => 'يومي',
            },
            'time' => $this->time,
            'day_of_week' => $this->day_of_week,
            'day_of_month' => $this->day_of_month,
            'retention_days' => $this->retention_days,
            'is_active' => (bool) $this->is_active,
            'last_run_at' => $this->last_run_at?->toDateTimeString(),
            'next_run_at' => $this->next_run_at?->toDateTimeString(),
        ];
    }
}
