<?php

namespace App\Modules\WhatsApp\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The datetime casts below are invisible to static analysis without this, so the
 * 24-hour window check reads as calling Carbon methods on a string.
 *
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $last_inbound_at
 */
class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'wa_id',
        'name',
        'last_seen_at',
        'last_inbound_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            // Opens Meta's 24-hour customer service window. Kept separate from
            // last_seen_at, which is touched for unrelated reasons.
            'last_inbound_at' => 'datetime',
        ];
    }

    /** @return HasMany<WhatsAppMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'contact_id');
    }

    public static function findOrCreateByWaId(string $waId, ?string $name = null): self
    {
        return static::firstOrCreate(
            ['wa_id' => $waId],
            ['name' => $name]
        );
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
