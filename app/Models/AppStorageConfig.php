<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class AppStorageConfig extends Model
{
    use AuditsChanges, ExposesPolicyFlags;

    protected $fillable = [
        'name',
        'driver',
        'config',
        'is_active',
        'priority',
        'cdn_url',
        'max_backups',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'max_backups' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<StorageDiskMapping, $this> */
    public function diskMappings(): HasMany
    {
        return $this->hasMany(StorageDiskMapping::class, 'primary_storage_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDecryptedConfig(): array
    {
        if (blank($this->attributes['config'] ?? null)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($this->attributes['config']), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            // Legacy / plain JSON during seeding edge cases
            $decoded = json_decode((string) $this->attributes['config'], true);

            return is_array($decoded) ? $decoded : [];
        }
    }

    public function setConfigAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $filtered = array_filter(
                $value,
                static fn ($item) => $item !== null && $item !== '',
            );
            $this->attributes['config'] = Crypt::encryptString(json_encode($filtered, JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->attributes['config'] = $value;
    }

    /**
     * Merge new config with existing secrets when blank on update.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeConfigKeepingSecrets(array $incoming): array
    {
        $existing = $this->getDecryptedConfig();
        $secretKeys = [
            'secret_access_key',
            'access_key_id',
            'password',
            'token',
            'api_key',
            'client_secret',
            'refresh_token',
        ];

        foreach ($secretKeys as $key) {
            if (array_key_exists($key, $incoming) && ($incoming[$key] === null || $incoming[$key] === '')) {
                if (array_key_exists($key, $existing)) {
                    $incoming[$key] = $existing[$key];
                } else {
                    unset($incoming[$key]);
                }
            }
        }

        return array_merge($existing, array_filter(
            $incoming,
            static fn ($item) => $item !== null && $item !== '',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $config = $this->getDecryptedConfig();
        $safeConfig = $config;

        foreach (['secret_access_key', 'password', 'token', 'api_key', 'client_secret', 'refresh_token'] as $secret) {
            unset($safeConfig[$secret]);
        }

        if (isset($safeConfig['access_key_id']) && is_string($safeConfig['access_key_id'])) {
            $key = $safeConfig['access_key_id'];
            $safeConfig['access_key_id'] = strlen($key) > 8
                ? substr($key, 0, 4).str_repeat('*', max(strlen($key) - 8, 0)).substr($key, -4)
                : str_repeat('*', strlen($key));
        }

        return [
            'id' => $this->id,
            'can' => $this->policyFlags(),
            'name' => $this->name,
            'driver' => $this->driver,
            'driver_label' => config("storage.drivers.{$this->driver}", $this->driver),
            'config' => $safeConfig,
            'has_secret_access_key' => filled($config['secret_access_key'] ?? null),
            'has_access_key_id' => filled($config['access_key_id'] ?? null),
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'cdn_url' => $this->cdn_url,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
