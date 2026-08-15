<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function scopeOfGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function getValueAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ($value === '1' || $value === 'true'),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;

            return;
        }

        $this->attributes['value'] = match ($this->type ?? 'string') {
            'integer' => (string) $value,
            'boolean' => $value ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    public static function set(string $key, $value, string $type, string $group, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key, 'group' => $group],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
            ]
        );
    }

    public static function get(string $key, string $group, $default = null)
    {
        $setting = static::query()
            ->where('key', $key)
            ->where('group', $group)
            ->first();

        return $setting?->value ?? $default;
    }
}
