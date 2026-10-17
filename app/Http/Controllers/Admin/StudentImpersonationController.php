<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\StudentImpersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use InvalidArgumentException;

/**
 * Issues the one-time "open this student's account" link from the users list.
 */
class StudentImpersonationController extends Controller implements HasMiddleware
{
    public function __construct(private readonly StudentImpersonation $impersonation) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:admin.users.impersonate')];
    }

    public function store(Request $request, User $user): JsonResponse
    {
        try {
            $link = $this->impersonation->createLink($request->user(), $user);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'url' => $link['url'],
            'expires_at' => $link['expires_at']->toIso8601String(),
            'expires_in_minutes' => StudentImpersonation::TTL_MINUTES,
            'student' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }
}
