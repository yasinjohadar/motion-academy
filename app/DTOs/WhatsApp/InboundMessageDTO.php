<?php

namespace App\DTOs\WhatsApp;

class InboundMessageDTO
{
    public function __construct(
        public string $messageId,
        public string $from,
        public int $timestamp,
        public string $type,
        public ?string $textBody = null,
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'from' => $this->from,
            'timestamp' => $this->timestamp,
            'type' => $this->type,
            'text_body' => $this->textBody,
            'metadata' => $this->metadata,
        ];
    }
}
