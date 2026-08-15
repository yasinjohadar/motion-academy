<?php

namespace App\Services\WhatsApp\Meta;

use App\DTOs\WhatsApp\InboundMessageDTO;
use App\DTOs\WhatsApp\StatusUpdateDTO;

/**
 * Parses Meta Cloud API webhook payloads into the same DTOs the Evolution parser
 * produces, so ProcessWhatsAppWebhookEventJob stays provider-agnostic.
 *
 * Documented nesting: entry[].changes[].value.{messages[],statuses[]}
 */
class MetaWebhookParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{messages: array<int, InboundMessageDTO>, statuses: array<int, StatusUpdateDTO>}
     */
    public function parse(array $payload): array
    {
        $messages = [];
        $statuses = [];

        foreach ($this->values($payload) as $value) {
            foreach ($value['messages'] ?? [] as $message) {
                if ($dto = $this->toInbound($message)) {
                    $messages[] = $dto;
                }
            }

            foreach ($value['statuses'] ?? [] as $status) {
                if ($dto = $this->toStatus($status)) {
                    $statuses[] = $dto;
                }
            }
        }

        return compact('messages', 'statuses');
    }

    public function isMetaPayload(array $payload): bool
    {
        return ($payload['object'] ?? null) === 'whatsapp_business_account'
            || (isset($payload['entry']) && is_array($payload['entry']));
    }

    /**
     * Flattens entry[].changes[].value, ignoring changes for other fields.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function values(array $payload): array
    {
        $values = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? 'messages') !== 'messages') {
                    continue;
                }

                if (is_array($change['value'] ?? null)) {
                    $values[] = $change['value'];
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function toInbound(array $message): ?InboundMessageDTO
    {
        $id = $message['id'] ?? null;
        $from = $message['from'] ?? null;

        if (! is_string($id) || ! is_string($from)) {
            return null;
        }

        $type = (string) ($message['type'] ?? 'text');

        return new InboundMessageDTO(
            messageId: $id,
            from: $from,
            timestamp: (int) ($message['timestamp'] ?? 0),
            type: $type,
            textBody: $this->extractText($message, $type),
            metadata: [
                'provider' => 'meta',
                'context' => $message['context'] ?? null,
                'raw_type_payload' => $message[$type] ?? null,
            ],
        );
    }

    /**
     * Text lives under a different key per message type; captions double as the body
     * for media so the inbox is not left blank.
     *
     * @param  array<string, mixed>  $message
     */
    protected function extractText(array $message, string $type): ?string
    {
        return match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null,
            'image', 'video', 'document', 'audio' => $message[$type]['caption'] ?? null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function toStatus(array $status): ?StatusUpdateDTO
    {
        $id = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (! is_string($id) || ! is_string($state)) {
            return null;
        }

        return new StatusUpdateDTO(
            messageId: $id,
            status: $state,
            timestamp: (int) ($status['timestamp'] ?? 0),
            recipientId: (string) ($status['recipient_id'] ?? ''),
            conversation: is_array($status['conversation'] ?? null) ? $status['conversation'] : null,
            pricing: is_array($status['pricing'] ?? null) ? $status['pricing'] : null,
        );
    }
}
