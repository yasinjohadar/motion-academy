<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Turns a country code + local number from a form into the canonical number,
 * with the same messages everywhere a phone is entered (register, profile, admin).
 */
class PhoneInput
{
    /**
     * @throws ValidationException
     */
    public static function canonical(?string $countryCode, ?string $local, ?int $ignoreUserId = null, string $field = 'phone'): string
    {
        if (blank($local)) {
            throw ValidationException::withMessages([$field => 'رقم الهاتف مطلوب.']);
        }

        $canonical = PhoneNumber::normalize($countryCode ?: (string) config('phone.default_country'), $local);

        if ($canonical === null) {
            throw ValidationException::withMessages([$field => 'رقم الهاتف غير صحيح. تأكد من مفتاح الدولة والرقم.']);
        }

        $taken = User::query()
            ->where('phone_e164', $canonical)
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([$field => 'رقم الهاتف مستخدم في حساب آخر.']);
        }

        return $canonical;
    }

    /**
     * For single free-text phone fields (admin user forms): "+963 991…", "0991…",
     * "00963…" all resolve, local numbers assume the default country.
     *
     * @throws ValidationException
     */
    public static function canonicalFromAny(?string $raw, ?int $ignoreUserId = null, string $field = 'phone'): string
    {
        $canonical = PhoneNumber::fromAny($raw);

        if ($canonical === null) {
            throw ValidationException::withMessages([$field => 'رقم الهاتف غير صحيح. اكتبه بمفتاح الدولة، مثل +963991234567.']);
        }

        $taken = User::query()
            ->where('phone_e164', $canonical)
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([$field => 'رقم الهاتف مستخدم في حساب آخر.']);
        }

        return $canonical;
    }

    /**
     * Split a stored canonical number back into the two form fields.
     *
     * @return array{country_code: string, phone: string}
     */
    public static function split(?string $canonical): array
    {
        if (! PhoneNumber::isValid($canonical)) {
            return ['country_code' => (string) config('phone.default_country'), 'phone' => ''];
        }

        $country = PhoneNumber::countryOf((string) $canonical);

        return ['country_code' => $country, 'phone' => substr((string) $canonical, strlen($country))];
    }
}
