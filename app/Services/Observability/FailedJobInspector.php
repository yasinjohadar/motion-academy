<?php

namespace App\Services\Observability;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The failed_jobs table, which nothing in the application read before this.
 *
 * Read via the query builder rather than a model: the table is framework-owned and
 * has no Eloquent model in Laravel.
 */
class FailedJobInspector
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(?string $queue = null): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->when($queue, fn ($q) => $q->where('queue', $queue))
            ->orderByDesc('failed_at')
            ->paginate((int) config('observability.per_page', 25))
            ->withQueryString()
            ->through(fn (object $row): array => $this->toAdminArray($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(object $row): array
    {
        $payload = json_decode((string) $row->payload, true) ?: [];

        return [
            'id' => $row->id,
            'uuid' => $row->uuid,
            'connection' => $row->connection,
            'queue' => $row->queue,
            'job' => $payload['displayName'] ?? 'غير معروف',
            'job_label' => class_basename($payload['displayName'] ?? 'غير معروف'),
            'attempts' => $payload['attempts'] ?? null,
            'exception_summary' => $this->exceptionSummary((string) $row->exception),
            'exception' => Str::limit((string) $row->exception, 4000),
            'failed_at' => $row->failed_at,
        ];
    }

    /**
     * First line of the trace — the class and message, without the stack.
     */
    protected function exceptionSummary(string $exception): string
    {
        $firstLine = Str::before($exception, "\n");

        return Str::limit(trim($firstLine), 180);
    }

    public function find(string $uuid): ?object
    {
        return DB::table('failed_jobs')->where('uuid', $uuid)->first();
    }

    public function retry(string $uuid): bool
    {
        if (! $this->find($uuid)) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return true;
    }

    public function retryAll(): int
    {
        $count = $this->count();

        if ($count > 0) {
            Artisan::call('queue:retry', ['id' => ['all']]);
        }

        return $count;
    }

    public function forget(string $uuid): bool
    {
        return DB::table('failed_jobs')->where('uuid', $uuid)->delete() > 0;
    }

    public function flush(): int
    {
        $count = $this->count();

        DB::table('failed_jobs')->delete();

        return $count;
    }

    public function count(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /**
     * Distinct queues present in the table, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public function queues(): array
    {
        return DB::table('failed_jobs')->distinct()->orderBy('queue')->pluck('queue')->all();
    }

    /**
     * Queue-health banner data. Mirrors BackupController::queueMeta(), but reports the
     * queue list the worker actually has to listen on — a bare `queue:work` silently
     * ignores the broadcast, mail and whatsapp queues.
     *
     * @return array<string, mixed>
     */
    public function queueHealth(): array
    {
        $connection = (string) config('queue.default', 'sync');

        return [
            'connection' => $connection,
            'needs_worker' => ! in_array($connection, ['sync', 'null'], true),
            'worker_command' => 'php artisan queue:work --queue=broadcast,default,mail,whatsapp',
            'pending' => $connection === 'database' ? DB::table('jobs')->count() : null,
        ];
    }
}
