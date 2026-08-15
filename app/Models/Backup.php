<?php

namespace App\Models;

use App\Models\Concerns\ExposesPolicyFlags;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends Model
{
    use ExposesPolicyFlags, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'backup_type',
        'storage_driver',
        'storage_config_id',
        'storage_path',
        'file_path',
        'file_size',
        'compression_type',
        'status',
        'progress',
        'stage',
        'started_at',
        'completed_at',
        'duration',
        'error_message',
        'metadata',
        'retention_days',
        'expires_at',
        'schedule_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
            'progress' => 'integer',
            'duration' => 'integer',
            'retention_days' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function storageConfig(): BelongsTo
    {
        return $this->belongsTo(AppStorageConfig::class, 'storage_config_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BackupSchedule::class, 'schedule_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BackupLog::class)->latest('id');
    }

    public function calculateExpiresAt(): ?CarbonInterface
    {
        if (! $this->retention_days) {
            return null;
        }

        return now()->addDays($this->retention_days);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(bool $withLogs = false): array
    {
        $data = [
            'id' => $this->id,
            'can' => $this->policyFlags(['update', 'delete', 'restore']),
            'name' => $this->name,
            'type' => $this->type,
            'backup_type' => $this->backup_type,
            'backup_type_label' => match ($this->backup_type) {
                'database' => 'قاعدة البيانات',
                'files' => 'الملفات',
                'full' => 'كاملة',
                default => $this->backup_type,
            },
            'storage_driver' => $this->storage_driver,
            'storage_config_id' => $this->storage_config_id,
            'storage_config' => $this->storageConfig
                ? [
                    'id' => $this->storageConfig->id,
                    'name' => $this->storageConfig->name,
                    'driver' => $this->storageConfig->driver,
                    'driver_label' => config("storage.drivers.{$this->storageConfig->driver}", $this->storageConfig->driver),
                ]
                : null,
            'storage_path' => $this->storage_path,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'file_size_human' => $this->humanFileSize(),
            'compression_type' => $this->compression_type,
            'status' => $this->status,
            'status_label' => $this->statusLabel($this->status),
            'progress' => (int) $this->progress,
            'stage' => $this->stage,
            'stage_label' => $this->stageLabel($this->stage),
            'started_at' => $this->started_at?->toDateTimeString(),
            'completed_at' => $this->completed_at?->toDateTimeString(),
            'duration' => $this->duration,
            'error_message' => $this->error_message,
            'retention_days' => $this->retention_days,
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'schedule_id' => $this->schedule_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'creator' => $this->creator?->only(['id', 'name']),
            'restore' => $this->restoreState(),
        ];

        if ($withLogs) {
            $data['logs'] = $this->logs()
                ->limit(100)
                ->get()
                ->map(fn (BackupLog $log) => [
                    'id' => $log->id,
                    'level' => $log->level,
                    'message' => $log->message,
                    'created_at' => $log->created_at?->toDateTimeString(),
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    public function humanFileSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function restoreState(): ?array
    {
        $restore = $this->metadata['restore'] ?? null;
        if (! is_array($restore)) {
            return null;
        }

        $status = $restore['status'] ?? null;
        $stage = $restore['stage'] ?? null;

        return [
            'status' => $status,
            'status_label' => $this->statusLabel(is_string($status) ? $status : null),
            'progress' => (int) ($restore['progress'] ?? 0),
            'stage' => $stage,
            'stage_label' => $this->restoreStageLabel(is_string($stage) ? $stage : null),
            'error' => $restore['error'] ?? null,
            'updated_at' => $restore['updated_at'] ?? null,
            'finished_at' => $restore['finished_at'] ?? null,
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'بانتظار الطابور',
            'running' => 'جارية',
            'completed' => 'مكتملة',
            'failed' => 'فاشلة',
            default => $status ?: '—',
        };
    }

    public function stageLabel(?string $stage): string
    {
        return match ($stage) {
            'queued' => 'في انتظار عامل الطابور',
            'starting' => 'بدء العملية',
            'dumping_database' => 'تصدير قاعدة البيانات',
            'copying_files' => 'نسخ الملفات',
            'compressing' => 'ضغط الأرشيف',
            'uploading' => 'الرفع إلى التخزين',
            'completed' => 'اكتملت',
            'failed' => 'فشلت',
            'stuck' => 'توقفت بسبب المهلة',
            default => $stage ?: '—',
        };
    }

    public function restoreStageLabel(?string $stage): string
    {
        return match ($stage) {
            'queued' => 'في انتظار عامل الطابور',
            'safety_snapshot' => 'إنشاء لقطة أمان',
            'downloading' => 'تنزيل الأرشيف',
            'extracting' => 'فك الضغط',
            'applying' => 'تطبيق الاسترجاع',
            'rollback' => 'الرجوع من لقطة الأمان',
            'completed' => 'اكتمل الاسترجاع',
            'failed' => 'فشل الاسترجاع',
            default => $stage ?: '—',
        };
    }
}
