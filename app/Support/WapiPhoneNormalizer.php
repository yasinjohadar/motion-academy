<?php

namespace App\Support;

final class WapiPhoneNormalizer
{
    /**
     * Normalize to digits only (Flaxxa examples use country + number without +).
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits;
    }

    public static function isValidE164Digits(string $digits): bool
    {
        if ($digits === '') {
            return false;
        }

        return (bool) preg_match('/^[1-9]\d{6,14}$/', $digits);
    }
}
