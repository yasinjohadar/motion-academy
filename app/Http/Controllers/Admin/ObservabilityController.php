<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Observability\ActivityLogViewer;
use App\Services\Observability\DatabaseInspector;
use App\Services\Observability\FailedJobInspector;
use App\Services\Observability\SecurityLogger;
use App\Services\Observability\SessionInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ObservabilityController extends Controller implements HasMiddleware
{
    public function __construct(
        private ActivityLogViewer $activity,
        private SessionInspector $sessions,
        private FailedJobInspector $failedJobs,
        private DatabaseInspector $db,
    ) {}

    public static function middleware(): array
    {
        return [
            (new Middleware('permission:admin.observability.activity|admin.observability.security|admin.observability.logs|admin.observability.jobs|admin.observability.database'))
                ->only('index'),
            (new Middleware('permission:admin.observability.activity'))->only(['activity', 'show']),
            (new Middleware('permission:admin.observability.security'))
                ->only(['security', 'sessions', 'revokeSession']),
            (new Middleware('permission:admin.observability.jobs'))
                ->only(['failedJobs', 'retryJob', 'retryAllJobs', 'forgetJob', 'flushJobs']),
            (new Middleware('permission:admin.observability.database'))
                ->only(['database', 'databaseTable']),
        ];
    }

    /**
     * Hub: the counters that tell you whether anything needs attention right now.
     */
    public function index(Request $request): Response
    {
        $since = now()->subDay();

        return Inertia::render('Admin/Observability/Index', [
            'stats' => [
                'audit_today' => Activity::query()
                    ->where('log_name', '!=', SecurityLogger::LOG_NAME)
                    ->where('created_at', '>=', $since)->count(),
                'failed_logins_today' => Activity::query()
                    ->where('log_name', SecurityLogger::LOG_NAME)
                    ->whereIn('event', ['login_failed', 'lockout'])
                    ->where('created_at', '>=', $since)->count(),
                'failed_jobs' => $this->failedJobs->count(),
                'active_sessions' => $this->sessions->activeCount(),
                'unprocessed_webhooks' => $this->unprocessedWebhookCount(),
                'log_files' => $this->logFileCount(),
            ],
            'queue' => $this->failedJobs->queueHealth(),
            'flash' => $this->flash($request),
        ]);
    }

    public function activity(Request $request): Response
    {
        $filters = $this->filters($request, ['log_name', 'event', 'causer', 'search']);

        return Inertia::render('Admin/Observability/Activity', [
            'items' => $this->activity->auditTrail($filters),
            'filters' => $filters,
            'options' => $this->activity->filterOptions(security: false),
            'flash' => $this->flash($request),
        ]);
    }

    public function security(Request $request): Response
    {
        $filters = $this->filters($request, ['event', 'causer', 'search']);

        return Inertia::render('Admin/Observability/Security', [
            'items' => $this->activity->securityTrail($filters),
            'filters' => $filters,
            'options' => $this->activity->filterOptions(security: true),
            'flash' => $this->flash($request),
        ]);
    }

    public function show(Request $request, int $activity): Response
    {
        $record = Activity::query()->with('causer:id,name,email')->findOrFail($activity);

        // Sibling rows from the same batch, so a save + syncRoles reads as one change.
        $batch = $record->batch_uuid
            ? Activity::query()->where('batch_uuid', $record->batch_uuid)
                ->where('id', '!=', $record->id)->get()
                ->map(fn (Activity $a): array => $this->activity->toAdminArray($a))->all()
            : [];

        return Inertia::render('Admin/Observability/Show', [
            'item' => $this->activity->toAdminArray($record),
            'batch' => $batch,
        ]);
    }

    public function sessions(Request $request): Response
    {
        return Inertia::render('Admin/Observability/Sessions', [
            'items' => $this->sessions->available() ? $this->sessions->paginate() : null,
            'driver' => config('session.driver'),
            'flash' => $this->flash($request),
        ]);
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        if ($session === $request->session()->getId()) {
            return back()->with('error', 'لا يمكنك إبطال جلستك الحالية من هنا — استخدم تسجيل الخروج.');
        }

        return $this->sessions->revoke($session)
            ? back()->with('success', 'تم إبطال الجلسة.')
            : back()->with('error', 'الجلسة غير موجودة.');
    }

    public function failedJobs(Request $request): Response
    {
        $queue = $request->string('queue')->toString() ?: null;

        return Inertia::render('Admin/Observability/FailedJobs', [
            'items' => $this->failedJobs->paginate($queue),
            'filters' => ['queue' => $queue],
            'queues' => $this->failedJobs->queues(),
            'queue' => $this->failedJobs->queueHealth(),
            'flash' => $this->flash($request),
        ]);
    }

    public function retryJob(string $uuid): RedirectResponse
    {
        return $this->failedJobs->retry($uuid)
            ? back()->with('success', 'تم إعادة جدولة المهمة.')
            : back()->with('error', 'المهمة غير موجودة.');
    }

    public function retryAllJobs(): RedirectResponse
    {
        $count = $this->failedJobs->retryAll();

        return back()->with('success', $count > 0
            ? "تم إعادة جدولة {$count} مهمة."
            : 'لا توجد مهام فاشلة.');
    }

    public function forgetJob(string $uuid): RedirectResponse
    {
        return $this->failedJobs->forget($uuid)
            ? back()->with('success', 'تم حذف المهمة.')
            : back()->with('error', 'المهمة غير موجودة.');
    }

    public function flushJobs(): RedirectResponse
    {
        $count = $this->failedJobs->flush();

        return redirect()->route('admin.observability.failed-jobs')
            ->with('success', "تم حذف {$count} مهمة فاشلة.");
    }

    /**
     * Database connection details, table sizes and configuration warnings.
     * Read-only by design — there is no VACUUM/OPTIMIZE action here.
     */
    public function database(Request $request): Response
    {
        $tables = $this->db->tables();

        return Inertia::render('Admin/Observability/Database', [
            'connection' => $this->db->connection(),
            'tables' => $tables,
            'totals' => $this->db->totals(tables: $tables),
            'notes' => $this->db->driverNotes(tables: $tables),
            'flash' => $this->flash($request),
        ]);
    }

    public function databaseTable(Request $request, string $table): JsonResponse
    {
        $detail = $this->db->table($table);

        if (! $detail) {
            return response()->json(['message' => 'الجدول غير موجود.'], 404);
        }

        return response()->json($detail);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, ?string>
     */
    protected function filters(Request $request, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [
                $key => $request->string($key)->toString() ?: null,
            ])
            ->all();
    }

    /**
     * @return array<string, ?string>
     */
    protected function flash(Request $request): array
    {
        return [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
        ];
    }

    protected function unprocessedWebhookCount(): int
    {
        try {
            return DB::table('whatsapp_webhook_events')->whereNull('processed_at')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function logFileCount(): int
    {
        return count(glob(storage_path('logs/*.log')) ?: []);
    }
}
