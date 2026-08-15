<?php

namespace App\Services\Observability;

use App\Models\User;
use Illuminate\Cache\RateLimiter;

/**
 * Writes security events into the shared `activity_log` table under log_name=security.
 *
 * One unified timeline is what an investigation actually needs ("what happened around
 * 3pm?"), so security events live beside model changes rather than in their own table.
 * The trade-off is that filtering by IP becomes an unindexed JSON query, and retention
 * must be per-log_name — handled by PruneObservabilityCommand.
 */
class SecurityLogger
{
    public const LOG_NAME = 'security';

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Record a security event.
     *
     * @param  User|null  $user  The account involved. Null for unauthenticated events
     *                           such as a failed login, where there is no causer.
     * @param  array<string, mixed>  $properties
     * @param  string|null  $throttleBy  When given, at most one row per this key per
     *                                   `observability.security_throttle_seconds`.
     */
    public function record(
        string $event,
        string $description,
        ?User $user = null,
        array $properties = [],
        ?string $throttleBy = null,
    ): void {
        if ($throttleBy !== null && $this->isThrottled($event, $throttleBy)) {
            return;
        }

        $activity = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties([...$this->requestContext(), ...$properties]);

        if ($user) {
            // performedOn as well as causedBy: for events like a role change the
            // subject and the actor can be different people.
            $activity = $activity->causedBy($user)->performedOn($user);
        }

        $activity->log($description);
    }

    /**
     * Client details for the event.
     *
     * Deliberately not gated behind runningInConsole(): Symfony populates REMOTE_ADDR
     * even in CLI, so the check buys nothing while making the IP untestable. A change
     * made from the console is instead identifiable by its null `changed_by`.
     *
     * @return array<string, mixed>
     */
    protected function requestContext(): array
    {
        $request = request();

        return array_filter([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * A single credential-stuffing run would otherwise write thousands of rows.
     */
    protected function isThrottled(string $event, string $key): bool
    {
        $cacheKey = 'security-log:'.$event.':'.$key;
        $seconds = (int) config('observability.security_throttle_seconds', 60);

        if ($this->limiter->tooManyAttempts($cacheKey, maxAttempts: 1)) {
            return true;
        }

        $this->limiter->hit($cacheKey, decaySeconds: $seconds);

        return false;
    }
}
