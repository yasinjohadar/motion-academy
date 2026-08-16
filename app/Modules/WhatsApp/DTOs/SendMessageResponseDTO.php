<?php

namespace App\Modules\WhatsApp\DTOs;

class SendMessageResponseDTO
{
    public function __construct(
        public string $metaMessageId,
        public array $rawResponse = []
    ) {}

    public function toArray(): array
    {
        return [
            'meta_message_id' => $this->metaMessageId,
            'raw_response' => $this->rawResponse,
        ];
    }
}
