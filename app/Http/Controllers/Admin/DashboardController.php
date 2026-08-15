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

        return Inertia::render('Admin/Dashboard', [
            'widgets' => $this->stats->overview(),
            'health' => $this->stats->systemHealth(),
            'todaySummary' => $this->stats->todaySummary($user),
            'activityChart' => $this->stats->activityChart(),
            'recentActivity' => $this->stats->recentActivity(),
            'lastLoginAt' => optional($this->stats->lastLoginAt($user))?->diffForHumans(),
        ]);
    }
}
