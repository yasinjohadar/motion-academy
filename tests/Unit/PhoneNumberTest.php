<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uses the application TestCase: country lookups read config/phone.php.
 */
class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function localNumbers(): array
    {
        return [
            'plain' => ['963', '991234567', '963991234567'],
            'trunk zero' => ['963', '0991234567', '963991234567'],
            'spaces and dashes' => ['963', '0991 234-567', '963991234567'],
            'plus on the country code' => ['+963', '991234567', '963991234567'],
            'full number pasted' => ['963', '+963 991 234 567', '963991234567'],
            'double-zero prefix pasted' => ['963', '00963991234567', '963991234567'],
            'saudi' => ['966', '0501234567', '966501234567'],
            'too short' => ['963', '12', null],
            'empty' => ['963', '', null],
            'letters only' => ['963', 'abc', null],
            'too long' => ['963', '99123456789012', null],
        ];
    }

    #[DataProvider('localNumbers')]
    public function test_normalize(string $country, string $local, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($country, $local));
    }

    public function test_from_any_handles_international_and_local_forms(): void
    {
        $this->assertSame('963991234567', PhoneNumber::fromAny('+963 991 234 567'));
        $this->assertSame('963991234567', PhoneNumber::fromAny('+963 0991 234 567'));
        $this->assertSame('963991234567', PhoneNumber::fromAny('00963991234567'));
        $this->assertSame('963991234567', PhoneNumber::fromAny('0991234567'));
        $this->assertSame('966501234567', PhoneNumber::fromAny('0501234567', '966'));
        $this->assertNull(PhoneNumber::fromAny(''));
        $this->assertNull(PhoneNumber::fromAny(null));
        $this->assertNull(PhoneNumber::fromAny('+1 23'));
    }

    public function test_mask_and_format(): void
    {
        $this->assertSame('+963 ••• ••• 567', PhoneNumber::mask('963991234567'));
        $this->assertSame('+963 991 234 567', PhoneNumber::format('963991234567'));
        $this->assertSame('', PhoneNumber::mask('123'));
    }

    public function test_country_of_prefers_the_longest_configured_code(): void
    {
        $this->assertSame('963', PhoneNumber::countryOf('963991234567'));
        $this->assertSame('1', PhoneNumber::countryOf('12025550123'));
        $this->assertSame('20', PhoneNumber::countryOf('201001234567'));
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(PhoneNumber::isValid('963991234567'));
        $this->assertFalse(PhoneNumber::isValid('0963991234567'));
        $this->assertFalse(PhoneNumber::isValid('9639912'));
        $this->assertFalse(PhoneNumber::isValid(null));
    }
}
