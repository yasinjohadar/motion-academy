<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorageDiskMappingStoreRequest;
use App\Http\Requests\Admin\StorageDiskMappingUpdateRequest;
use App\Models\AppStorageConfig;
use App\Models\StorageDiskMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class StorageDiskMappingController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware('admin', 'storage-disk-mappings');
    }

    public function index(Request $request): Response
    {
        $mappings = StorageDiskMapping::query()
            ->with('primaryStorage')
            ->orderBy('disk_name')
            ->get()
            ->map(fn (StorageDiskMapping $mapping) => $mapping->toAdminArray());

        return Inertia::render('Admin/StorageDiskMappings/Index', [
            'mappings' => $mappings,
            'logicalDisks' => config('storage.logical_disks', []),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/StorageDiskMappings/Create', [
            'logicalDisks' => config('storage.logical_disks', []),
            'storages' => $this->storageOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(StorageDiskMappingStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        StorageDiskMapping::query()->create([
            'disk_name' => $data['disk_name'],
            'label' => $data['label'],
            'primary_storage_id' => $data['primary_storage_id'],
            'fallback_storage_ids' => array_values($data['fallback_storage_ids'] ?? []),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.storage-disk-mappings.index')
            ->with('success', 'تم إنشاء ربط القرص بنجاح.');
    }

    public function edit(Request $request, StorageDiskMapping $storage_disk_mapping): Response
    {
        $storage_disk_mapping->load('primaryStorage');

        return Inertia::render('Admin/StorageDiskMappings/Edit', [
            'mapping' => $storage_disk_mapping->toAdminArray(),
            'logicalDisks' => config('storage.logical_disks', []),
            'storages' => $this->storageOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(
        StorageDiskMappingUpdateRequest $request,
        StorageDiskMapping $storage_disk_mapping,
    ): RedirectResponse {
        $data = $request->validated();

        $storage_disk_mapping->update([
            'disk_name' => $data['disk_name'],
            'label' => $data['label'],
            'primary_storage_id' => $data['primary_storage_id'],
            'fallback_storage_ids' => array_values($data['fallback_storage_ids'] ?? []),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.storage-disk-mappings.index')
            ->with('success', 'تم تحديث ربط القرص بنجاح.');
    }

    public function destroy(StorageDiskMapping $storage_disk_mapping): RedirectResponse
    {
        $storage_disk_mapping->delete();

        return redirect()
            ->route('admin.storage-disk-mappings.index')
            ->with('success', 'تم حذف ربط القرص.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storageOptions(): array
    {
        return AppStorageConfig::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (AppStorageConfig $config) => [
                'id' => $config->id,
                'name' => $config->name,
                'driver' => $config->driver,
                'driver_label' => config("storage.drivers.{$config->driver}", $config->driver),
            ])
            ->all();
    }
}
