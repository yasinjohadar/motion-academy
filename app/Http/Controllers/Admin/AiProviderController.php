<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiProviderProbeRequest;
use App\Http\Requests\Admin\AiProviderStoreRequest;
use App\Http\Requests\Admin\AiProviderTestRequest;
use App\Http\Requests\Admin\AiProviderUpdateRequest;
use App\Models\AiProvider;
use App\Services\Ai\AiConnectionTester;
use App\Services\Ai\AiProviderConfigApplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'ai-providers',
            false,
            [
                'admin.ai-providers.test' => ['test', 'testDraft', 'setDefault', 'toggleActive'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $providers = AiProvider::query()
            ->latest('id')
            ->get()
            ->map(fn (AiProvider $provider) => $provider->toAdminArray());

        return Inertia::render('Admin/AiProviders/Index', [
            'providers' => $providers,
            'drivers' => AiProvider::DRIVERS,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/AiProviders/Create', [
            'drivers' => AiProvider::DRIVERS,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(AiProviderStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $provider = AiProvider::query()->create([
            ...$data,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($provider->is_default) {
            $provider->setAsDefault();
        }

        AiProviderConfigApplier::refresh();

        return redirect()
            ->route('admin.ai-providers.index')
            ->with('success', 'تم إنشاء مزود الذكاء الاصطناعي بنجاح.');
    }

    public function edit(Request $request, AiProvider $ai_provider): Response
    {
        return Inertia::render('Admin/AiProviders/Edit', [
            'provider' => $ai_provider->toAdminArray(),
            'drivers' => AiProvider::DRIVERS,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(AiProviderUpdateRequest $request, AiProvider $ai_provider): RedirectResponse
    {
        $data = $request->validated();

        if (! filled($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $makeDefault = (bool) ($data['is_default'] ?? $ai_provider->is_default);
        $isActive = (bool) ($data['is_active'] ?? $ai_provider->is_active);

        if ($ai_provider->is_default && ! $isActive) {
            return back()->with('error', 'لا يمكن تعطيل المزود الافتراضي. عيّن مزوداً افتراضياً آخر أولاً.');
        }

        $ai_provider->fill([
            ...$data,
            'is_active' => $isActive,
            'is_default' => $makeDefault,
        ])->save();

        if ($makeDefault) {
            $ai_provider->setAsDefault();
        }

        AiProviderConfigApplier::refresh();

        return redirect()
            ->route('admin.ai-providers.index')
            ->with('success', 'تم تحديث مزود الذكاء الاصطناعي بنجاح.');
    }

    public function destroy(AiProvider $ai_provider): RedirectResponse
    {
        if ($ai_provider->is_default) {
            return back()->with('error', 'لا يمكن حذف المزود الافتراضي. عيّن مزوداً آخر كافتراضي أولاً.');
        }

        $ai_provider->delete();
        AiProviderConfigApplier::refresh();

        return redirect()
            ->route('admin.ai-providers.index')
            ->with('success', 'تم حذف مزود الذكاء الاصطناعي بنجاح.');
    }

    public function setDefault(AiProvider $ai_provider): RedirectResponse
    {
        $ai_provider->setAsDefault();
        AiProviderConfigApplier::refresh();

        return back()->with('success', 'تم تعيين المزود كافتراضي.');
    }

    public function toggleActive(AiProvider $ai_provider): RedirectResponse
    {
        if ($ai_provider->is_default && $ai_provider->is_active) {
            return back()->with('error', 'لا يمكن تعطيل المزود الافتراضي. عيّن مزوداً افتراضياً آخر أولاً.');
        }

        $ai_provider->update(['is_active' => ! $ai_provider->is_active]);
        AiProviderConfigApplier::refresh();

        return back()->with(
            'success',
            $ai_provider->is_active ? 'تم تفعيل المزود.' : 'تم تعطيل المزود.',
        );
    }

    public function test(
        AiProviderTestRequest $request,
        AiProvider $ai_provider,
        AiConnectionTester $tester,
    ): RedirectResponse {
        $result = $tester->probeProvider(
            $ai_provider,
            $request->validated('api_key'),
        );

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? $result['message'].(isset($result['sample']) ? ' الرد: '.$result['sample'] : '')
                : $result['message'],
        );
    }

    public function testDraft(AiProviderProbeRequest $request, AiConnectionTester $tester): RedirectResponse
    {
        $result = $tester->probe($request->validated());

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? $result['message'].(isset($result['sample']) ? ' الرد: '.$result['sample'] : '')
                : $result['message'],
        );
    }
}
