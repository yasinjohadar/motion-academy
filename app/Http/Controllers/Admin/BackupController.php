<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackupRestoreRequest;
use App\Http\Requests\Admin\BackupStoreRequest;
use App\Jobs\CreateBackupJob;
use App\Jobs\RestoreBackupJob;
use App\Models\AppStorageConfig;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(
        private readonly BackupService $backupService,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'backups',
            true,
            [
                'admin.backups.download' => ['download'],
                'admin.backups.restore' => ['restore'],
                'admin.backups.create' => ['run', 'runSync'],
                'show' => ['status'],
            ],
        );
    }

    /**
     * @return array{connection: string, needs_worker: bool, worker_command: string, dispatch_sync: bool}
     */
    private function queueMeta(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $dispatchSync = (bool) config('backup.dispatch_sync', false);

        return [
            'connection' => $connection,
            'needs_worker' => ! $dispatchSync && ! in_array($connection, ['sync', 'null'], true),
            'worker_command' => 'php artisan queue:work',
            'dispatch_sync' => $dispatchSync,
        ];
    }

    private function dispatchBackupJob(int $backupId): void
    {
        if (config('backup.dispatch_sync') || config('queue.default') === 'sync') {
            CreateBackupJob::dispatchSync($backupId);

            return;
        }

        CreateBackupJob::dispatch($backupId);
    }

    private function dispatchRestoreJob(int $backupId): void
    {
        if (config('backup.dispatch_sync') || config('queue.default') === 'sync') {
            RestoreBackupJob::dispatchSync($backupId);

            return;
        }

        RestoreBackupJob::dispatch($backupId);
    }

    public function index(Request $request): Response
    {
        $backups = Backup::query()
            ->with(['storageConfig', 'creator'])
            ->latest('id')
            ->paginate(15)
            ->through(fn (Backup $backup) => $backup->toAdminArray());

        return Inertia::render('Admin/Backups/Index', [
            'backups' => $backups,
            'queue' => $this->queueMeta(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Backups/Create', [
            'storages' => $this->storageOptions(),
            'queue' => $this->queueMeta(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(BackupStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $storage = AppStorageConfig::query()->findOrFail($data['storage_config_id']);

        $backup = Backup::query()->create([
            'name' => filled($data['name'] ?? null)
                ? $data['name']
                : 'backup_'.now()->format('Y-m-d_H-i-s'),
            'type' => 'manual',
            'backup_type' => $data['backup_type'],
            'storage_driver' => $storage->driver,
            'storage_config_id' => $storage->id,
            'compression_type' => 'zip',
            'status' => 'pending',
            'progress' => 0,
            'stage' => 'queued',
            'retention_days' => (int) ($data['retention_days'] ?? 30),
            'created_by' => $request->user()?->id,
            'expires_at' => now()->addDays((int) ($data['retention_days'] ?? 30)),
        ]);

        $this->dispatchBackupJob($backup->id);

        $queue = $this->queueMeta();
        $message = $queue['needs_worker']
            ? 'تم إرسال النسخة إلى الطابور. شغّل: php artisan queue:work ثم راقب التقدم هنا.'
            : 'تم بدء إنشاء النسخة الاحتياطية.';

        return redirect()
            ->route('admin.backups.show', $backup)
            ->with('success', $message);
    }

    public function show(Request $request, Backup $backup): Response
    {
        $backup->load(['storageConfig', 'creator']);

        return Inertia::render('Admin/Backups/Show', [
            'backup' => $backup->toAdminArray(withLogs: true),
            'queue' => $this->queueMeta(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function status(Backup $backup): JsonResponse
    {
        $backup->load('storageConfig');

        return response()->json($backup->toAdminArray(withLogs: true));
    }

    public function download(Backup $backup): StreamedResponse
    {
        return $this->backupService->download($backup);
    }

    public function restore(BackupRestoreRequest $request, Backup $backup): RedirectResponse
    {
        if ($backup->status !== 'completed') {
            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', 'يمكن استرجاع النسخ المكتملة فقط.');
        }

        $restore = $backup->restoreState();
        if (in_array($restore['status'] ?? null, ['pending', 'running'], true)) {
            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', 'عملية استرجاع جارية بالفعل.');
        }

        $meta = $backup->metadata ?? [];
        $meta['restore'] = [
            'status' => 'pending',
            'progress' => 0,
            'stage' => 'queued',
            'error' => null,
            'updated_at' => now()->toDateTimeString(),
        ];
        $backup->update(['metadata' => $meta]);

        $this->dispatchRestoreJob($backup->id);

        $queue = $this->queueMeta();
        $message = $queue['needs_worker']
            ? 'تم إرسال الاسترجاع إلى الطابور. تأكد أن queue:work يعمل لرؤية التقدم مباشرة.'
            : 'بدء الاسترجاع — راقب شريط التقدم والسجل.';

        return redirect()
            ->route('admin.backups.show', $backup)
            ->with('success', $message);
    }

    public function run(Backup $backup): RedirectResponse
    {
        if (! in_array($backup->status, ['failed', 'pending'], true)) {
            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', 'يمكن إعادة التشغيل للنسخ الفاشلة أو المعلقة فقط.');
        }

        $backup->update([
            'status' => 'pending',
            'progress' => 0,
            'stage' => 'queued',
            'error_message' => null,
            'completed_at' => null,
        ]);

        $this->dispatchBackupJob($backup->id);

        return redirect()
            ->route('admin.backups.show', $backup)
            ->with('success', 'تمت إعادة تشغيل النسخة.');
    }

    public function runSync(Backup $backup): RedirectResponse
    {
        if (! in_array($backup->status, ['failed', 'pending'], true)) {
            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', 'التشغيل المباشر متاح للمعلقة أو الفاشلة فقط.');
        }

        $backup->update([
            'status' => 'pending',
            'progress' => 0,
            'stage' => 'starting',
            'error_message' => null,
            'completed_at' => null,
        ]);

        try {
            CreateBackupJob::dispatchSync($backup->id);

            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('success', 'تم تشغيل النسخة مباشرة بدون طابور.');
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', 'فشل التشغيل المباشر: '.$e->getMessage());
        }
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        $this->authorize('delete', $backup);

        $this->backupService->deleteBackup($backup);

        return redirect()
            ->route('admin.backups.index')
            ->with('success', 'تم حذف النسخة الاحتياطية.');
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
