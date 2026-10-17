<?php

namespace App\Http\Middleware;

use App\Support\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The control panel is for admins. A student who reaches /admin (an old link,
 * a typed URL) is sent to their own dashboard on the public site instead.
 */
class EnsureAdminPanelAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (AccountType::isAdmin($request->user())) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'لوحة التحكم مخصصة للإدارة.');
        }

        return redirect()->route('student.dashboard');
    }
}
