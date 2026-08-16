<?php

namespace App\Modules\WhatsApp\DTOs;

class StatusUpdateDTO
{
    public function __construct(
        public string $messageId,
        public string $status,
        public int $timestamp,
        public string $recipientId,
        public ?array $conversation = null,
        public ?array $pricing = null
    ) {}

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'recipient_id' => $this->recipientId,
            'conversation' => $this->conversation,
            'pricing' => $this->pricing,
        ];
    }
}
