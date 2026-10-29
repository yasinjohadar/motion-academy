<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupScheduleStoreRequest;
use App\Http\Requests\Admin\BackupScheduleUpdateRequest;
use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Services\Backup\BackupScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class BackupScheduleController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(private readonly BackupScheduleService $scheduleService) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'backup-schedules',
            false,
            [
                'admin.backup-schedules.edit' => ['toggleActive', 'execute'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $schedules = BackupSchedule::query()
            ->with('storageConfig')
            ->orderBy('name')
            ->get()
            ->map(fn (BackupSchedule $schedule) => $schedule->toAdminArray());

        return Inertia::render('Admin/BackupSchedules/Index', [
            'schedules' => $schedules,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/BackupSchedules/Create', [
            'storages' => $this->storageOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(BackupScheduleStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $schedule = BackupSchedule::query()->create([
            ...$data,
            'storage_config_id' => $request->storageConfigId(),
            'retention_days' => (int) ($data['retention_days'] ?? 30),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $request->user()?->id,
            'next_run_at' => null,
        ]);

        $schedule->update([
            'next_run_at' => $schedule->calculateNextRunAt(),
        ]);

        return redirect()
            ->route('admin.backup-schedules.index')
            ->with('success', 'تم إنشاء جدولة النسخ بنجاح.');
    }

    public function edit(Request $request, BackupSchedule $backup_schedule): Response
    {
        $this->authorize('update', $backup_schedule);

        $backup_schedule->load('storageConfig');

        return Inertia::render('Admin/BackupSchedules/Edit', [
            'schedule' => $backup_schedule->toAdminArray(),
            'storages' => $this->storageOptions(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(BackupScheduleUpdateRequest $request, BackupSchedule $backup_schedule): RedirectResponse
    {
        $this->authorize('update', $backup_schedule);

        $data = $request->validated();

        $backup_schedule->fill([
            ...$data,
            'storage_config_id' => $request->storageConfigId(),
            'retention_days' => (int) ($data['retention_days'] ?? 30),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $backup_schedule->next_run_at = $backup_schedule->calculateNextRunAt();
        $backup_schedule->save();

        return redirect()
            ->route('admin.backup-schedules.index')
            ->with('success', 'تم تحديث الجدولة بنجاح.');
    }

    public function destroy(BackupSchedule $backup_schedule): RedirectResponse
    {
        $this->authorize('delete', $backup_schedule);

        $backup_schedule->delete();

        return redirect()
            ->route('admin.backup-schedules.index')
            ->with('success', 'تم حذف الجدولة.');
    }

    public function toggleActive(BackupSchedule $backup_schedule): RedirectResponse
    {
        $this->authorize('update', $backup_schedule);

        $active = ! $backup_schedule->is_active;

        $backup_schedule->update([
            'is_active' => $active,
            'next_run_at' => $active ? $backup_schedule->calculateNextRunAt() : $backup_schedule->next_run_at,
        ]);

        return redirect()
            ->route('admin.backup-schedules.index')
            ->with('success', 'تم تحديث حالة الجدولة.');
    }

    public function execute(BackupSchedule $backup_schedule): RedirectResponse
    {
        $this->authorize('update', $backup_schedule);

        $backup = $this->scheduleService->dispatchSchedule($backup_schedule);

        return redirect()
            ->route('admin.backups.show', $backup)
            ->with('success', 'تم تشغيل الجدولة يدوياً.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storageOptions(): array
    {
        return Backup::destinationOptions();
    }
}
