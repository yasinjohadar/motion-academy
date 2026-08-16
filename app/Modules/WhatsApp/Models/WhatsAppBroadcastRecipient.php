<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBroadcastRecipient extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_broadcast_recipients';

    protected $fillable = [
        'broadcast_id',
        'user_id',
        'phone',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
