<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class StorageDiskMapping extends Model
{
    use AuditsChanges;

    protected $fillable = [
        'disk_name',
        'label',
        'primary_storage_id',
        'fallback_storage_ids',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fallback_storage_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<AppStorageConfig, $this> */
    public function primaryStorage(): BelongsTo
    {
        return $this->belongsTo(AppStorageConfig::class, 'primary_storage_id');
    }

    /**
     * @return Collection<int, AppStorageConfig>
     */
    public function getFallbackStorages(): Collection
    {
        $ids = $this->fallback_storage_ids ?? [];

        if ($ids === []) {
            return collect();
        }

        return AppStorageConfig::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'disk_name' => $this->disk_name,
            'label' => $this->label,
            'primary_storage_id' => $this->primary_storage_id,
            'primary_storage' => $this->primaryStorage
                ? [
                    'id' => $this->primaryStorage->id,
                    'name' => $this->primaryStorage->name,
                    'driver' => $this->primaryStorage->driver,
                    'driver_label' => config("storage.drivers.{$this->primaryStorage->driver}", $this->primaryStorage->driver),
                ]
                : null,
            'fallback_storage_ids' => $this->fallback_storage_ids ?? [],
            'is_active' => (bool) $this->is_active,
        ];
    }
}
