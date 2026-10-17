<?php

namespace App\Support;

/**
 * International phone numbers as one canonical form: digits only, country code
 * first, no "+" — e.g. "963991234567". That is what `users.phone_e164` stores and
 * what WhatsApp (Evolution) expects.
 */
class PhoneNumber
{
    /**
     * Build the canonical number from a country code and a local number.
     *
     * Handles what people actually type: spaces, dashes, "+", a leading "00", a
     * national trunk zero ("0991…"), or the full number pasted into the local field.
     */
    public static function normalize(?string $countryCode, ?string $local): ?string
    {
        $country = self::digits((string) $countryCode);
        $number = self::digits((string) $local);

        if ($number === '') {
            return null;
        }

        // "00963…" international prefix.
        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        }

        // The whole international number was typed into the local field.
        if ($country !== '' && str_starts_with($number, $country) && strlen($number) > strlen($country) + 6) {
            $number = substr($number, strlen($country));
        }

        // National trunk zero: 0991… → 991…
        $number = ltrim($number, '0');

        $full = $country.$number;

        return self::isValid($full) ? $full : null;
    }

    /**
     * Normalize a number stored without a separate country code (legacy `phone`
     * values, admin input), assuming the default country for local numbers.
     */
    public static function fromAny(?string $raw, ?string $defaultCountry = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $digits = self::digits($raw);
        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');

        if ($international) {
            $digits = ltrim(str_starts_with($digits, '00') ? substr($digits, 2) : $digits, '0');

            return self::repairTrunkZero($digits);
        }

        return self::normalize($defaultCountry ?? (string) config('phone.default_country', '963'), $digits);
    }

    public static function isValid(?string $canonical): bool
    {
        return is_string($canonical) && preg_match('/^[1-9]\d{7,14}$/', $canonical) === 1;
    }

    /**
     * "+963 ••• ••• 567" — enough to recognise your own number, not to read someone else's.
     */
    public static function mask(?string $canonical): string
    {
        if (! self::isValid($canonical)) {
            return '';
        }

        $country = self::countryOf($canonical);

        return '+'.$country.' ••• ••• '.substr($canonical, -3);
    }

    /**
     * "+963 991 234 567" for display.
     */
    public static function format(?string $canonical): string
    {
        if (! self::isValid($canonical)) {
            return (string) $canonical;
        }

        $country = self::countryOf($canonical);
        $local = substr($canonical, strlen($country));

        return '+'.$country.' '.trim(chunk_split($local, 3, ' '));
    }

    /**
     * The longest configured country code the number starts with.
     */
    public static function countryOf(string $canonical): string
    {
        $codes = collect(config('phone.countries', []))->pluck('code')->sortByDesc(fn ($code) => strlen((string) $code));

        foreach ($codes as $code) {
            if (str_starts_with($canonical, (string) $code)) {
                return (string) $code;
            }
        }

        return substr($canonical, 0, 3);
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * "9630991…" stored with the trunk zero after the country code → "963991…".
     */
    private static function repairTrunkZero(string $digits): ?string
    {
        $country = self::countryOf($digits);
        $rest = substr($digits, strlen($country));

        if (str_starts_with($rest, '0')) {
            $digits = $country.ltrim($rest, '0');
        }

        return self::isValid($digits) ? $digits : null;
    }
}
