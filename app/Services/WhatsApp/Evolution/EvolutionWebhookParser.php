<?php

namespace App\Services\WhatsApp\Evolution;

use App\DTOs\WhatsApp\InboundMessageDTO;
use App\DTOs\WhatsApp\StatusUpdateDTO;
use App\Support\WhatsAppRecipientNormalizer;

class EvolutionWebhookParser
{
    /**
     * @return array{messages: InboundMessageDTO[], statuses: StatusUpdateDTO[]}
     */
    public function parse(array $payload): array
    {
        $messages = [];
        $statuses = [];

        $event = strtolower((string) ($payload['event'] ?? ''));
        $data = $payload['data'] ?? $payload;

        if ($this->isInboundEvent($event, $data)) {
            foreach ($this->extractInboundMessages($data) as $item) {
                $messages[] = $item;
            }
        }

        if ($this->isStatusEvent($event, $data)) {
            foreach ($this->extractStatuses($data, $event) as $item) {
                $statuses[] = $item;
            }
        }

        return compact('messages', 'statuses');
    }

    public function isEvolutionPayload(array $payload): bool
    {
        if (isset($payload['instance']) && isset($payload['event'])) {
            return true;
        }

        $event = strtolower((string) ($payload['event'] ?? ''));

        return str_contains($event, 'messages.')
            || str_contains($event, 'connection.')
            || str_contains($event, 'send_message');
    }

    public function isConnectionUpdate(array $payload): bool
    {
        $event = strtolower((string) ($payload['event'] ?? ''));

        return str_contains($event, 'connection');
    }

    public function extractConnectionState(array $payload): ?string
    {
        $state = data_get($payload, 'data.state')
            ?? data_get($payload, 'data.status')
            ?? data_get($payload, 'state');

        return is_string($state) ? strtolower($state) : null;
    }

    protected function isInboundEvent(string $event, mixed $data): bool
    {
        if (str_contains($event, 'messages.upsert') || str_contains($event, 'messages_upsert')) {
            return true;
        }

        return is_array($data) && (isset($data['key']) || isset($data['messages']));
    }

    protected function isStatusEvent(string $event, mixed $data): bool
    {
        if (str_contains($event, 'messages.update') || str_contains($event, 'messages_update')) {
            return true;
        }

        return is_array($data) && (isset($data['status']) || isset($data['update']));
    }

    /**
     * @return InboundMessageDTO[]
     */
    protected function extractInboundMessages(mixed $data): array
    {
        $items = [];
        $records = [];

        if (is_array($data) && isset($data['messages']) && is_array($data['messages'])) {
            $records = $data['messages'];
        } elseif (is_array($data) && isset($data['key'])) {
            $records = [$data];
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $key = $record['key'] ?? [];
            $fromMe = (bool) ($key['fromMe'] ?? $record['fromMe'] ?? false);
            if ($fromMe) {
                continue;
            }

            $messageId = (string) ($key['id'] ?? $record['id'] ?? '');
            $from = $this->resolveSenderJid($key, $record);
            if ($messageId === '' || $from === '') {
                continue;
            }

            if (WhatsAppRecipientNormalizer::isLikelyGroupRecipient($from)) {
                continue;
            }

            $message = $record['message'] ?? $record;
            $textBody = (string) (
                data_get($message, 'conversation')
                ?? data_get($message, 'extendedTextMessage.text')
                ?? data_get($message, 'imageMessage.caption')
                ?? data_get($message, 'videoMessage.caption')
                ?? data_get($message, 'documentMessage.caption')
                ?? ''
            );

            $type = 'text';
            if (isset($message['imageMessage'])) {
                $type = 'image';
            } elseif (isset($message['videoMessage'])) {
                $type = 'video';
            } elseif (isset($message['audioMessage'])) {
                $type = 'audio';
            } elseif (isset($message['documentMessage'])) {
                $type = 'document';
            } elseif (isset($message['stickerMessage'])) {
                $type = 'sticker';
            } elseif (isset($message['locationMessage'])) {
                $type = 'location';
            }

            $items[] = new InboundMessageDTO(
                messageId: $messageId,
                from: $from,
                timestamp: (int) ($record['messageTimestamp'] ?? $record['timestamp'] ?? time()),
                type: $type,
                textBody: $textBody !== '' ? $textBody : null,
                metadata: ['provider_payload' => $record]
            );
        }

        return $items;
    }

    /**
     * Resolve a replyable sender JID/number from Evolution/Baileys payload fields.
     */
    protected function resolveSenderJid(array $key, array $record): string
    {
        $candidates = [
            $key['remoteJidAlt'] ?? null,
            $key['participant'] ?? null,
            $record['participant'] ?? null,
            data_get($record, 'senderPn'),
            data_get($record, 'sender'),
            $key['remoteJid'] ?? null,
            $record['remoteJid'] ?? null,
            $record['from'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);

            if (WhatsAppRecipientNormalizer::isLikelyGroupRecipient($candidate)) {
                continue;
            }

            if (WhatsAppRecipientNormalizer::isValidIndividualJid($candidate)) {
                return $candidate;
            }

            if (! str_contains($candidate, '@')) {
                try {
                    return WhatsAppRecipientNormalizer::normalizeForEvolution($candidate);
                } catch (\Throwable) {
                    continue;
                }
            }

            if (str_ends_with($candidate, '@lid') && preg_match('/^[\w.\-]+@lid$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return StatusUpdateDTO[]
     */
    protected function extractStatuses(mixed $data, string $event): array
    {
        $items = [];
        $records = is_array($data) && isset($data['messages']) && is_array($data['messages'])
            ? $data['messages']
            : (is_array($data) ? [$data] : []);

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $key = $record['key'] ?? [];
            $messageId = (string) ($key['id'] ?? $record['id'] ?? '');
            $status = strtolower((string) (
                data_get($record, 'update.status')
                ?? data_get($record, 'status')
                ?? str_replace('messages.', '', $event)
            ));

            if ($messageId === '' || $status === '') {
                continue;
            }

            $statusMap = [
                'server_ack' => 'sent',
                'delivery_ack' => 'delivered',
                'read' => 'read',
                'played' => 'read',
                'error' => 'failed',
            ];
            $normalized = $statusMap[$status] ?? $status;

            $items[] = new StatusUpdateDTO(
                messageId: $messageId,
                status: $normalized,
                timestamp: (int) ($record['timestamp'] ?? time()),
                recipientId: (string) ($key['remoteJid'] ?? 'unknown'),
                conversation: null,
                pricing: null
            );
        }

        return $items;
    }
}
