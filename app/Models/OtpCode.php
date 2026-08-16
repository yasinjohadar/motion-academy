<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 */
class OtpCode extends Model
{
    protected $fillable = [
        'identifier',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip',
    ];

    /**
     * The hash is never exposed — not in JSON, not in an audit payload.
     *
     * @var list<string>
     */
    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFor(Builder $query, string $identifier, OtpPurpose $purpose): Builder
    {
        return $query->where('identifier', $identifier)->where('purpose', $purpose->value);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
