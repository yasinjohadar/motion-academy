<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\ExposesPolicyFlags;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppStorageConfig extends Model
{
    use AuditsChanges, ExposesPolicyFlags;

    /** Never sent back to the browser; kept when the form leaves them blank. */
    public const SECRET_KEYS = ['access_key_id', 'secret_access_key'];

    /** Flags kept across edits: legacy_root marks files stored before zone prefixes existed. */
    public const INTERNAL_KEYS = ['legacy_root'];

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

    /** @return HasMany<Backup, $this> */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class, 'storage_config_id');
    }

    /** @return HasMany<BackupSchedule, $this> */
    public function backupSchedules(): HasMany
    {
        return $this->hasMany(BackupSchedule::class, 'storage_config_id');
    }

    /**
     * The account settings, secrets included.
     *
     * A row that cannot be decrypted (e.g. APP_KEY was rotated) is an error, not
     * an empty config: an empty config would silently build a disk with no
     * credentials and fail somewhere far away.
     *
     * @return array<string, mixed>
     */
    public function getDecryptedConfig(): array
    {
        if (blank($this->attributes['config'] ?? null)) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($this->attributes['config']), true);
        } catch (DecryptException $e) {
            Log::channel('integrations')->error('Storage account settings cannot be decrypted', [
                'storage' => $this->name,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException("تعذّر فك تشفير إعدادات التخزين «{$this->name}». أعد إدخال المفاتيح.", previous: $e);
        }

        return is_array($decoded) ? $decoded : [];
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
     * The config to save on update: what the form sent, with each blank secret
     * keeping its stored value (the form never echoes secrets back).
     *
     * Non-secret fields come only from the form, so clearing one really clears
     * it, and a driver change starts from scratch instead of carrying keys from
     * the previous provider.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function mergeConfigKeepingSecrets(array $incoming, ?string $driver = null): array
    {
        if ($driver !== null && $driver !== $this->driver) {
            return $incoming;
        }

        $existing = $this->getDecryptedConfig();

        foreach (self::SECRET_KEYS as $key) {
            if (blank($incoming[$key] ?? null) && filled($existing[$key] ?? null)) {
                $incoming[$key] = $existing[$key];
            }
        }

        // Set by the system, never by the form (see AppStorageFactory).
        foreach (self::INTERNAL_KEYS as $key) {
            if (array_key_exists($key, $existing)) {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        try {
            $config = $this->getDecryptedConfig();
            $configError = false;
        } catch (RuntimeException) {
            $config = [];
            $configError = true;
        }

        $safeConfig = array_diff_key($config, array_flip(self::SECRET_KEYS));
        $key = (string) ($config['access_key_id'] ?? '');
        $keyHint = strlen($key) > 8 ? substr($key, 0, 4).'…'.substr($key, -4) : ($key !== '' ? '••••' : null);

        return [
            'id' => $this->id,
            'can' => $this->policyFlags(),
            'name' => $this->name,
            'driver' => $this->driver,
            'driver_label' => config("storage.drivers.{$this->driver}", $this->driver),
            'config' => $safeConfig,
            'config_error' => $configError,
            'access_key_hint' => $keyHint,
            'has_secret_access_key' => filled($config['secret_access_key'] ?? null),
            'has_access_key_id' => filled($config['access_key_id'] ?? null),
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'cdn_url' => $this->cdn_url,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
