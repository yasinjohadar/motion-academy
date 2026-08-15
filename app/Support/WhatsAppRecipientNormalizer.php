<?php

namespace App\Support;

use InvalidArgumentException;

class WhatsAppRecipientNormalizer
{
    /**
     * Normalize recipient based on selected provider.
     */
    public static function normalize(string $provider, string $recipient): string
    {
        $trimmed = trim($recipient);
        if ($trimmed === '') {
            throw new InvalidArgumentException('رقم المستلم أو JID مطلوب.');
        }

        return match ($provider) {
            'custom_api', 'evolution' => self::normalizeForCustomApi($trimmed),
            default => self::normalizeAsE164($trimmed),
        };
    }

    /**
     * Wasender-compatible formatting:
     * - Keep valid JID as-is (individual/group)
     * - Otherwise normalize to digits without "+"
     */
    public static function normalizeForEvolution(string $recipient): string
    {
        return self::normalizeForCustomApi($recipient);
    }

    public static function normalizeForCustomApi(string $recipient): string
    {
        if (self::isValidGroupJid($recipient) || self::isValidIndividualJid($recipient)) {
            return $recipient;
        }

        if (str_ends_with($recipient, '@lid') && preg_match('/^[\w.\-]+@lid$/', $recipient) === 1) {
            return $recipient;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('صيغة المستلم غير صالحة. أدخل رقمًا دوليًا صحيحًا أو JID صحيحًا.');
        }

        return ltrim($digits, '0');
    }

    public static function normalizeAsE164(string $recipient): string
    {
        if (self::isValidGroupJid($recipient) || self::isValidIndividualJid($recipient)) {
            return $recipient;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';
        $digits = ltrim($digits, '0');
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
            throw new InvalidArgumentException('رقم الهاتف غير صالح.');
        }

        return '+'.$digits;
    }

    public static function isLikelyGroupRecipient(string $recipient): bool
    {
        return str_ends_with($recipient, '@g.us');
    }

    public static function isValidGroupJid(string $recipient): bool
    {
        return preg_match('/^\d{10,20}\-\d{10,20}@g\.us$/', $recipient) === 1;
    }

    public static function isValidIndividualJid(string $recipient): bool
    {
        return preg_match('/^\d{7,20}@(s\.whatsapp\.net|c\.us)$/', $recipient) === 1;
    }

    /**
     * Whether we can send a direct reply to this recipient (not a group, has a usable JID/number).
     */
    public static function isReplyableRecipient(string $recipient): bool
    {
        $recipient = trim($recipient);
        if ($recipient === '' || self::isLikelyGroupRecipient($recipient)) {
            return false;
        }

        if (self::isValidIndividualJid($recipient)) {
            return true;
        }

        if (str_ends_with($recipient, '@lid')) {
            return preg_match('/^[\w.\-]+@lid$/', $recipient) === 1;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';

        return $digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 15;
    }
}
