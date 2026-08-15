<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'event_id',
        'provider',
        'event_type',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the indexed column in step with the payload it is derived from.
        static::saving(function (self $event): void {
            if (blank($event->event_type)) {
                $type = data_get($event->payload, 'event');

                if (is_string($type) && $type !== '') {
                    $event->event_type = mb_substr($type, 0, 80);
                }
            }
        });
    }

    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'instance' => data_get($this->payload, 'instance'),
            'is_processed' => $this->isProcessed(),
            'processed_at' => optional($this->processed_at)?->format('Y-m-d H:i:s'),
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i:s'),
            'created_at_human' => optional($this->created_at)?->diffForHumans(),
            'payload' => json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
