<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller implements HasMiddleware
{
    public function __construct(private DashboardStats $stats) {}

    public static function middleware(): array
    {
        return [
            (new Middleware('permission:admin.dashboard.view'))->only('index'),
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        // The dashboard is the stat tiles and the shortcuts only; activity, checks and
        // today's summary live on the observability screens.
        return Inertia::render('Admin/Dashboard', [
            'widgets' => $this->stats->overview(),
            'lastLoginAt' => optional($this->stats->lastLoginAt($user))?->diffForHumans(),
        ]);
    }
}
