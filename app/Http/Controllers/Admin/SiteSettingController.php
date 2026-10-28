<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSettingUpdateRequest;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Services\Frontend\HomeSections;
use App\Services\Site\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingController extends Controller implements HasMiddleware
{
    public function __construct(
        private SiteSettingsService $settingsService,
        private HomeSections $homeSections,
    ) {}

    public static function middleware(): array
    {
        return [
            (new Middleware('permission:admin.site-settings.list|admin.site-settings.edit'))->only(['edit']),
            (new Middleware('permission:admin.site-settings.edit'))->only(['update']),
        ];
    }

    public function edit(Request $request): Response
    {
        $setting = SiteSetting::current();
        $socialLinks = SocialLink::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SocialLink $link) => $link->toAdminArray())
            ->values();

        return Inertia::render('Admin/SiteSettings/Edit', [
            'setting' => $setting->toAdminArray(),
            'socialLinks' => $socialLinks,
            'iconOptions' => config('site.social_icons', []),
            'homeSections' => $this->homeSections->toAdminArray(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(SiteSettingUpdateRequest $request): RedirectResponse
    {
        $setting = SiteSetting::current();
        $data = $request->validated();

        $this->settingsService->update(
            $setting,
            $data,
            $request->file('logo'),
            $request->file('favicon'),
        );

        $this->homeSections->save($data['home_sections'] ?? []);

        return redirect()
            ->route('admin.site-settings.edit')
            ->with('success', 'تم حفظ إعدادات الموقع بنجاح.');
    }
}
