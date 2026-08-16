<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'direction',
        // Written through update() in WhatsAppOutboundSendService, i.e. mass assignment —
        // leaving it out of here would silently keep the column null.
        'provider',
        'contact_id',
        'meta_message_id',
        'type',
        'body',
        'status',
        'payload',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'error' => 'array',
        ];
    }

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    public const TYPE_TEXT = 'text';

    public const TYPE_TEMPLATE = 'template';

    public const TYPE_IMAGE = 'image';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_VIDEO = 'video';

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }
}
