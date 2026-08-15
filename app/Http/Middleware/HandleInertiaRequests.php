<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Services\Notifications\NotificationCenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $site = null;
        $socialLinks = [];

        try {
            $site = SiteSetting::current()->toPublicArray();
            $socialLinks = SocialLink::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['label', 'url', 'icon'])
                ->values();
        } catch (\Throwable) {
            // migrations may not have run yet
        }

        $queueConnection = (string) config('queue.default', 'sync');

        $notifications = ['unread_count' => 0, 'items' => [], 'realtime' => [
            'enabled' => false,
            'poll_interval' => (int) config('notifications.realtime.poll_interval', 30000),
        ]];

        if ($user) {
            try {
                $notifications = app(NotificationCenter::class)->snapshot($user);
            } catch (\Throwable) {
                // migrations may not have run yet
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => $user ? $user->getRoleNames()->values()->all() : [],
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'is_admin' => $user ? $user->hasRole('admin') : false,
            ],
            'site' => $site,
            'socialLinks' => $socialLinks,
            'queue' => [
                'connection' => $queueConnection,
                'needs_worker' => ! in_array($queueConnection, ['sync', 'null'], true),
                // Named queues are in use (notification channels, WhatsApp webhooks),
                // so the worker must be told about them or those jobs never run.
                'worker_command' => 'php artisan queue:work --queue=broadcast,default,mail,whatsapp',
            ],
            'notifications' => $notifications,
        ];
    }
}
