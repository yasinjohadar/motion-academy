<?php

use App\Http\Middleware\EnsureAdminPanelAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ExceptionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/webhooks/evolution',
            'api/webhooks/evolution/*',
            'api/webhooks/meta',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'admin.panel' => EnsureAdminPanelAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Attach request/user context to every reported exception so entries in
        // laravel.log can be attributed instead of being anonymous stack traces.
        // See App\Support\ExceptionContext for why runningInConsole() is the check.
        $exceptions->context(fn (): array => app()->runningInConsole()
            ? ExceptionContext::forConsole()
            : ExceptionContext::forRequest(request()));

        /*
         * A policy denial is a refused action inside a page the user may legitimately
         * be on, so it reads better as an inline message than an English 403 screen
         * (there are no custom error views yet).
         *
         * The type-hint is AccessDeniedHttpException, not AuthorizationException:
         * Handler::render() calls prepareException() BEFORE running these callbacks,
         * which rewraps a status-less AuthorizationException into this class, keeping
         * the message and setting the original as `previous`.
         *
         * Checking that `previous` is what keeps Spatie's UnauthorizedException out of
         * scope — that one means "you may not reach this screen at all", where a plain
         * 403 is the correct answer, and 20+ existing tests assert exactly that.
         */
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof AuthorizationException) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();

            // Laravel's untranslated default when a policy returns a bare false.
            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = 'لا تملك صلاحية تنفيذ هذا الإجراء.';
            }

            return back()->with('error', $message);
        });
    })->create();
