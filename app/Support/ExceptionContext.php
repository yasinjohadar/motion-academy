<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Builds the context array attached to every reported exception.
 *
 * Extracted from bootstrap/app.php so both branches are directly testable: the
 * console branch cannot be reached from an HTTP test and vice versa, since
 * Application::runningInConsole() has no setter.
 *
 * Note that Symfony populates REQUEST_METHOD / REMOTE_ADDR / HTTP_HOST with defaults
 * even under CLI, so `runningInConsole()` is the only reliable discriminator — a
 * server-variable check silently reports a fake URL for queue-worker failures.
 */
class ExceptionContext
{
    /**
     * Context for an exception raised while serving an HTTP request.
     *
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request): array
    {
        return array_filter([
            'user_email' => $request->user()?->email,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Context for an exception raised in a console command or queue worker.
     * The command line is more useful here than a synthesised request URL.
     *
     * @return array<string, mixed>
     */
    public static function forConsole(?array $argv = null): array
    {
        $arguments = array_slice($argv ?? ($_SERVER['argv'] ?? []), 1);
        $command = trim(implode(' ', $arguments));

        return array_filter([
            'console_command' => $command !== '' ? $command : null,
        ], fn ($value): bool => $value !== null);
    }
}
