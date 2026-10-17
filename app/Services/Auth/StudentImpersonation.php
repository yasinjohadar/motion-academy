<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use App\Support\AccountType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "Open this student's account": an admin gets a link to paste into another
 * browser (or a private window), which signs that browser in as the student.
 *
 * The link is a credential, so it is:
 *  - for student accounts only — never a way into another admin's account;
 *  - signed (tampering voids it), short-lived, and usable exactly once;
 *  - recorded in the security log when created and when used.
 */
class StudentImpersonation
{
    public const TTL_MINUTES = 15;

    /** Session key marking a browser that an admin opened as a student. */
    public const SESSION_KEY = 'impersonated_by';

    public function __construct(private readonly SecurityLogger $security) {}

    /**
     * @return array{url: string, expires_at: Carbon}
     *
     * @throws InvalidArgumentException when the account is not a student's
     */
    public function createLink(User $admin, User $student): array
    {
        if (AccountType::isAdmin($student)) {
            throw new InvalidArgumentException('لا يمكن الدخول إلى حساب أدمن بهذه الطريقة.');
        }

        $token = Str::random(48);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put($this->cacheKey($token), [
            'admin_id' => $admin->id,
            'student_id' => $student->id,
        ], $expiresAt);

        $this->security->record(
            event: 'impersonation_link_created',
            description: "إنشاء رابط دخول إلى حساب الطالب {$student->email}",
            user: $admin,
            properties: ['student_id' => $student->id, 'student_email' => $student->email, 'expires_at' => $expiresAt->toIso8601String()],
        );

        return [
            'url' => URL::temporarySignedRoute('impersonate.enter', $expiresAt, ['token' => $token]),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Use a link: returns the admin and student it was made for, or null when it
     * is unknown, expired, already used, or no longer points at a student.
     *
     * @return array{admin: User, student: User}|null
     */
    public function redeem(string $token): ?array
    {
        // pull(): read and delete in one step, so a link works only once.
        $payload = Cache::pull($this->cacheKey($token));

        if (! is_array($payload)) {
            return null;
        }

        $admin = User::query()->find($payload['admin_id'] ?? null);
        $student = User::query()->find($payload['student_id'] ?? null);

        // Re-checked at use: the account may have been made an admin since.
        if (! $admin || ! $student || AccountType::isAdmin($student) || ! AccountType::isAdmin($admin)) {
            return null;
        }

        $this->security->record(
            event: 'impersonation_started',
            description: "دخول الأدمن {$admin->email} إلى حساب الطالب {$student->email}",
            user: $admin,
            properties: ['student_id' => $student->id, 'student_email' => $student->email],
        );

        return ['admin' => $admin, 'student' => $student];
    }

    private function cacheKey(string $token): string
    {
        return 'impersonation:'.hash('sha256', $token);
    }
}
