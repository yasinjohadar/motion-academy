<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppStorageStoreRequest;
use App\Http\Requests\Admin\AppStorageTestRequest;
use App\Http\Requests\Admin\AppStorageUpdateRequest;
use App\Models\AppStorageConfig;
use App\Services\Storage\AppStorageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class AppStorageController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(private readonly AppStorageManager $storageManager) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'app-storage',
            false,
            [
                'admin.app-storage.test' => ['test', 'testDraft'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $configs = AppStorageConfig::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (AppStorageConfig $config) => $config->toAdminArray());

        return Inertia::render('Admin/AppStorage/Index', [
            'configs' => $configs,
            'drivers' => config('storage.drivers', []),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/AppStorage/Create', [
            'drivers' => config('storage.drivers', []),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(AppStorageStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        AppStorageConfig::query()->create([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'config' => $data['config'] ?? [],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'cdn_url' => $data['cdn_url'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تم إنشاء إعداد التخزين بنجاح.');
    }

    public function edit(Request $request, AppStorageConfig $app_storage): Response
    {
        $this->authorize('update', $app_storage);

        return Inertia::render('Admin/AppStorage/Edit', [
            'config' => $app_storage->toAdminArray(),
            'drivers' => config('storage.drivers', []),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(AppStorageUpdateRequest $request, AppStorageConfig $app_storage): RedirectResponse
    {
        $this->authorize('update', $app_storage);

        $data = $request->validated();
        $mergedConfig = $app_storage->mergeConfigKeepingSecrets($data['config'] ?? []);

        $app_storage->update([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'config' => $mergedConfig,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'cdn_url' => $data['cdn_url'] ?? null,
        ]);

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تم تحديث إعداد التخزين بنجاح.');
    }

    public function destroy(AppStorageConfig $app_storage): RedirectResponse
    {
        $this->authorize('delete', $app_storage);

        if ($app_storage->diskMappings()->exists()) {
            return redirect()
                ->route('admin.app-storage.index')
                ->with('error', 'لا يمكن حذف إعداد مرتبط بربط أقراص. أزل الربط أولاً.');
        }

        $app_storage->delete();

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تم حذف إعداد التخزين.');
    }

    public function test(AppStorageConfig $app_storage): RedirectResponse
    {
        $result = $this->storageManager->testConnection($app_storage);

        return redirect()
            ->back()
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function testDraft(AppStorageTestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $configData = $data['config'] ?? [];

        if (! empty($data['config_id'])) {
            $existing = AppStorageConfig::query()->find($data['config_id']);
            if ($existing) {
                $configData = $existing->mergeConfigKeepingSecrets($configData);
            }
        }

        $result = $this->storageManager->testDriver(
            $data['driver'],
            $configData,
            $data['cdn_url'] ?? null,
        );

        return redirect()
            ->back()
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
