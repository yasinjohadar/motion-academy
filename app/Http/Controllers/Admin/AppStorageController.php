<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppStorageStoreRequest;
use App\Http\Requests\Admin\AppStorageTestRequest;
use App\Http\Requests\Admin\AppStorageUpdateRequest;
use App\Jobs\MigrateStorageFiles;
use App\Models\AppStorageConfig;
use App\Models\StorageMigration;
use App\Services\Storage\AppStorageManager;
use App\Services\Storage\StorageMigrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where uploaded files live: this server, or one cloud account. Switching copies
 * every file first (MigrateStorageFiles) and only then moves the site over.
 */
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
                'admin.app-storage.switch' => ['switch'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $active = $this->storageManager->activeConfig();

        $configs = AppStorageConfig::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (AppStorageConfig $config) => [
                ...$config->toAdminArray(),
                'is_current' => $config->id === $active?->id,
            ]);

        $migration = StorageMigration::query()->with(['fromConfig', 'toConfig'])->latest('id')->first();

        return Inertia::render('Admin/AppStorage/Index', [
            'configs' => $configs,
            'current' => [
                'is_local' => $active === null,
                'id' => $active?->id,
                'name' => $active?->name,
                'driver_label' => $active ? config("storage.drivers.{$active->driver}", $active->driver) : null,
            ],
            'migration' => $migration?->toAdminArray(),
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
            'config' => $data['config'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'cdn_url' => $data['cdn_url'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تمت إضافة حساب التخزين. اضغط «اختبار» للتأكد منه قبل التبديل إليه.');
    }

    public function edit(Request $request, AppStorageConfig $app_storage): Response
    {
        $this->authorize('update', $app_storage);

        return Inertia::render('Admin/AppStorage/Edit', [
            'config' => [
                ...$app_storage->toAdminArray(),
                'is_current' => $app_storage->id === $this->storageManager->activeConfig()?->id,
            ],
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
        $isCurrent = $app_storage->id === $this->storageManager->activeConfig()?->id;

        // The site's files live there: it cannot be switched off or turned into
        // another provider without moving the files first.
        if ($isCurrent && (! ($data['is_active'] ?? true) || $data['driver'] !== $app_storage->driver)) {
            return back()->with('error', 'هذا هو التخزين المستخدم حالياً. انقل الملفات إلى تخزين آخر قبل إيقافه أو تغيير نوعه.');
        }

        $app_storage->update([
            'name' => $data['name'],
            'driver' => $data['driver'],
            'config' => $app_storage->mergeConfigKeepingSecrets($data['config'], $data['driver']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'cdn_url' => $data['cdn_url'] ?? null,
        ]);

        $this->storageManager->refresh();

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تم تحديث حساب التخزين بنجاح.');
    }

    public function destroy(AppStorageConfig $app_storage): RedirectResponse
    {
        $this->authorize('delete', $app_storage);

        $reason = match (true) {
            $app_storage->id === $this->storageManager->activeConfig()?->id => 'هذا هو التخزين المستخدم حالياً، وملفات الموقع عليه.',
            $this->migrationInProgressInvolves($app_storage) => 'عملية نقل ملفات جارية تستخدم هذا الحساب.',
            $app_storage->backups()->exists() => 'توجد نسخ احتياطية محفوظة عليه. احذفها أولاً.',
            $app_storage->backupSchedules()->exists() => 'جدولة نسخ احتياطي تستخدمه. غيّر وجهتها أولاً.',
            default => null,
        };

        if ($reason) {
            return redirect()
                ->route('admin.app-storage.index')
                ->with('error', 'لا يمكن حذف هذا الحساب: '.$reason);
        }

        $app_storage->delete();

        return redirect()
            ->route('admin.app-storage.index')
            ->with('success', 'تم حذف حساب التخزين.');
    }

    public function test(AppStorageConfig $app_storage): RedirectResponse
    {
        $this->authorize('view', $app_storage);

        $result = $this->storageManager->testConnection($app_storage);

        return redirect()
            ->back()
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function testDraft(AppStorageTestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $configData = $data['config'] ?? [];

        // Testing with a saved account's stored secrets needs the right to edit it.
        if (! empty($data['config_id'])) {
            $existing = AppStorageConfig::query()->findOrFail($data['config_id']);
            $this->authorize('update', $existing);
            $configData = $existing->mergeConfigKeepingSecrets($configData, $data['driver']);
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

    /**
     * Copies every uploaded file to the chosen storage, then switches the site to it.
     */
    public function switch(Request $request, StorageMigrator $migrator): RedirectResponse
    {
        $request->validate([
            'target' => ['required'],
        ]);

        $targetValue = $request->input('target');
        $target = $targetValue === 'local' ? null : AppStorageConfig::query()->find((int) $targetValue);

        if ($targetValue !== 'local' && ! $target) {
            return back()->with('error', 'حساب التخزين غير موجود.');
        }

        if ($target) {
            $test = $this->storageManager->testConnection($target);

            if (! $test['success']) {
                return back()->with('error', 'لم يبدأ النقل لأن اختبار الحساب فشل. '.$test['message']);
            }
        }

        try {
            $migration = $migrator->start($target, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        MigrateStorageFiles::dispatch($migration->id);

        return back()->with('success', 'بدأ نقل الملفات. سيبقى الموقع على التخزين الحالي حتى يكتمل النقل ثم يتحوّل تلقائياً.');
    }

    private function migrationInProgressInvolves(AppStorageConfig $config): bool
    {
        return StorageMigration::query()
            ->whereIn('status', [StorageMigration::STATUS_PENDING, StorageMigration::STATUS_RUNNING])
            ->where(fn ($q) => $q->where('from_config_id', $config->id)->orWhere('to_config_id', $config->id))
            ->exists();
    }
}
