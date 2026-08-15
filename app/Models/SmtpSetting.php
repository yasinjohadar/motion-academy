<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Services\Smtp\SmtpConfigApplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmtpSetting extends Model
{
    use AuditsChanges, SoftDeletes;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'is_default',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'port' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => SmtpConfigApplier::forget());
        static::deleted(fn () => SmtpConfigApplier::forget());
    }

    public function setAsDefault(): void
    {
        static::query()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->forceFill([
            'is_default' => true,
            'is_active' => true,
        ])->save();
    }

    public static function getDefault(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array{transport: string, scheme: ?string, host: string, port: int, username: string, password: string}
     */
    public function toMailerConfig(): array
    {
        $scheme = match ($this->encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            'none' => null,
            default => 'smtp',
        };

        return [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'host' => $this->host,
            'port' => (int) $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => null,
        ];
    }

    /**
     * Payload safe for Inertia (never includes password).
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'encryption' => $this->encryption,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'has_password' => filled($this->password),
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
        ];
    }
}
