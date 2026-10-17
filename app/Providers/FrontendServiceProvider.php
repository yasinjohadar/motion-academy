<?php

namespace App\Providers;

use App\Models\CourseEnrollment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Frontend\CourseCatalog;
use App\Services\Notifications\NotificationCenter;
use App\Services\Seo\SiteSeo;
use App\Support\AccountType;
use App\Support\PanelRedirector;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

/**
 * Wiring for the public Blade site (views under resources/views/frontend).
 *
 * Kept apart from the admin panel: the admin is an Inertia/React app, the public
 * site is server-rendered Blade with its own assets in public/frontend.
 */
class FrontendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One settings lookup per request, shared by every SEO tag on the page.
        $this->app->scoped(SiteSeo::class);
    }

    public function boot(): void
    {
        View::composer('frontend.*', function (ViewInstance $view): void {
            $view->with('fa', asset('frontend/assets'));
            $view->with('siteSettings', $this->siteSettings());
        });

        // The signed-in visitor's menu and the student dashboard sidebar share one lookup.
        View::composer(['frontend.layouts.navbar', 'frontend.layouts.student', 'frontend.student.*'], function (ViewInstance $view): void {
            // Cached on the request, not with once(): the provider outlives a request
            // (tests, Octane), and the visitor's account must not leak into the next one.
            $attributes = request()->attributes;
            if (! $attributes->has('frontend.account')) {
                $attributes->set('frontend.account', $this->account());
            }
            $view->with('account', $attributes->get('frontend.account'));
        });

        View::composer('frontend.layouts.footer', function (ViewInstance $view): void {
            $view->with('footerStats', once(fn () => $this->footerStats()));
        });
    }

    /**
     * Real catalogue numbers for the footer strip.
     *
     * @return array{courses: int, lessons: int, categories: int, hours: int}
     */
    private function footerStats(): array
    {
        try {
            $catalog = $this->app->make(CourseCatalog::class);
            $visible = $catalog->visible();

            return [
                'courses' => (clone $visible)->count(),
                'lessons' => (int) (clone $visible)->sum('lessons_count'),
                'categories' => $catalog->rootCategories()->count(),
                'hours' => (int) ceil((clone $visible)->sum('duration_seconds') / 3600),
            ];
        } catch (\Throwable) {
            return ['courses' => 0, 'lessons' => 0, 'categories' => 0, 'hours' => 0];
        }
    }

    /**
     * @return array{name: string, initial: string, email: string, unread: int, pending: int, adminUrl: string|null}|null
     */
    private function account(): ?array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return [
            'name' => $user->name,
            'initial' => mb_substr(trim($user->name) ?: '؟', 0, 1),
            'email' => $user->email,
            'unread' => $this->app->make(NotificationCenter::class)->unreadCount($user),
            'pending' => $user->enrollments()->where('status', CourseEnrollment::STATUS_PENDING)->count(),
            // Staff also get a way back to the admin panel.
            'adminUrl' => AccountType::isAdmin($user) ? PanelRedirector::url($user) : null,
        ];
    }

    /**
     * Site settings under the keys the frontend layout reads.
     *
     * @return array<string, string|null>
     */
    private function siteSettings(): array
    {
        // Every partial of a page shares one lookup.
        return once(fn () => $this->loadSiteSettings());
    }

    /**
     * @return array<string, string|null>
     */
    private function loadSiteSettings(): array
    {
        try {
            $site = SiteSetting::current()->toPublicArray();
        } catch (\Throwable) {
            $site = [];
        }

        return [
            'site_name' => $site['site_name'] ?? config('app.name'),
            'site_email' => $site['email'] ?? null,
            'site_phone' => $site['phone'] ?? null,
            'site_address' => $site['address'] ?? null,
            'logo_url' => $site['logo_url'] ?? null,
            'footer_text' => $site['footer_text'] ?? null,
        ];
    }
}
