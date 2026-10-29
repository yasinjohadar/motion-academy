<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Copying every uploaded file to a new storage, then switching the site to it.
 *
 * Datetime casts are invisible to static analysis without these.
 *
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 */
class StorageMigration extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'from_config_id',
        'to_config_id',
        'status',
        'total',
        'copied',
        'skipped',
        'failed',
        'last_error',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'copied' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AppStorageConfig, $this> */
    public function fromConfig(): BelongsTo
    {
        return $this->belongsTo(AppStorageConfig::class, 'from_config_id');
    }

    /** @return BelongsTo<AppStorageConfig, $this> */
    public function toConfig(): BelongsTo
    {
        return $this->belongsTo(AppStorageConfig::class, 'to_config_id');
    }

    /** @return HasMany<StorageMigrationFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(StorageMigrationFile::class);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $done = $this->copied + $this->skipped + $this->failed;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'from' => $this->fromConfig?->name ?? 'السيرفر المحلي',
            'to' => $this->toConfig?->name ?? 'السيرفر المحلي',
            'total' => $this->total,
            'copied' => $this->copied,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'percent' => $this->total > 0 ? (int) floor($done / $this->total * 100) : ($this->status === self::STATUS_COMPLETED ? 100 : 0),
            'last_error' => $this->last_error,
            'in_progress' => $this->isInProgress(),
            'started_at' => $this->started_at?->format('Y-m-d H:i'),
            'finished_at' => $this->finished_at?->format('Y-m-d H:i'),
        ];
    }
}
