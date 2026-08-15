<?php

namespace App\Services\Observability;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Read side of the activity log, for both the audit and security screens.
 *
 * Mirrors App\Services\Notifications\NotificationCenter: filter + paginate +
 * withQueryString + through(), so the Inertia pages can reuse the same table and
 * pagination markup.
 */
class ActivityLogViewer
{
    /**
     * Audit trail — everything except the security timeline.
     *
     * @param  array{log_name?: ?string, event?: ?string, causer?: ?string, search?: ?string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function auditTrail(array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->baseQuery($filters)->where('log_name', '!=', SecurityLogger::LOG_NAME)
        );
    }

    /**
     * Security timeline — logins, lockouts, role changes.
     *
     * @param  array{event?: ?string, causer?: ?string, search?: ?string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function securityTrail(array $filters = []): LengthAwarePaginator
    {
        return $this->paginate(
            $this->baseQuery($filters)->where('log_name', SecurityLogger::LOG_NAME)
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Activity>
     */
    protected function baseQuery(array $filters): Builder
    {
        $logName = $filters['log_name'] ?? null;
        $event = $filters['event'] ?? null;
        $causer = $filters['causer'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        return Activity::query()
            ->with('causer:id,name,email')
            ->when($logName, fn (Builder $q) => $q->where('log_name', $logName))
            ->when($event, fn (Builder $q) => $q->where('event', $event))
            ->when($causer, fn (Builder $q) => $q->where('causer_id', $causer))
            ->when($search !== '', function (Builder $q) use ($search): void {
                // properties is JSON, so this is an unindexed LIKE — acceptable at
                // admin-panel volume, and the only way to search IPs and emails.
                $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('description', 'like', "%{$search}%")
                        ->orWhere('subject_type', 'like', "%{$search}%")
                        ->orWhere('properties', 'like', "%{$search}%");
                });
            })
            ->latest('id');
    }

    /**
     * @param  Builder<Activity>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        return $query
            ->paginate((int) config('observability.per_page', 25))
            ->withQueryString()
            ->through(fn (Activity $activity): array => $this->toAdminArray($activity));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(Activity $activity): array
    {
        $properties = $activity->properties?->all() ?? [];

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'event' => $activity->event,
            'description' => $activity->description,
            'subject_type' => $activity->subject_type,
            'subject_label' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
                'email' => $activity->causer->email,
            ] : null,
            'ip' => $properties['ip'] ?? null,
            'user_agent' => isset($properties['user_agent'])
                ? Str::limit((string) $properties['user_agent'], 60)
                : null,
            'changes' => $this->changes($properties),
            'extra' => $this->extra($properties),
            'batch_uuid' => $activity->batch_uuid,
            'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $activity->created_at?->diffForHumans(),
        ];
    }

    /**
     * Field-by-field old → new pairs for the detail view.
     *
     * @param  array<string, mixed>  $properties
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    protected function changes(array $properties): array
    {
        $new = $properties['attributes'] ?? null;

        if (! is_array($new)) {
            return [];
        }

        $old = is_array($properties['old'] ?? null) ? $properties['old'] : [];

        return collect($new)
            ->map(fn (mixed $value, string $field): array => [
                'field' => $field,
                'old' => $this->scalarise($old[$field] ?? null),
                'new' => $this->scalarise($value),
            ])
            ->values()
            ->all();
    }

    /**
     * Properties that are not part of the old/new diff — IPs, attempted emails,
     * role lists. Rendered as a key/value list.
     *
     * @param  array<string, mixed>  $properties
     * @return array<int, array{key: string, value: string}>
     */
    protected function extra(array $properties): array
    {
        return collect($properties)
            ->except(['attributes', 'old', 'ip', 'user_agent'])
            ->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'value' => $this->scalarise($value),
            ])
            ->values()
            ->all();
    }

    protected function scalarise(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    /**
     * Distinct values for the filter dropdowns, taken from what is actually logged
     * rather than a hard-coded list that can drift.
     *
     * @return array{log_names: array<int, string>, events: array<int, string>}
     */
    public function filterOptions(bool $security = false): array
    {
        $base = fn () => Activity::query()->when(
            $security,
            fn (Builder $q) => $q->where('log_name', SecurityLogger::LOG_NAME),
            fn (Builder $q) => $q->where('log_name', '!=', SecurityLogger::LOG_NAME),
        );

        return [
            'log_names' => $base()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values()->all(),
            'events' => $base()->distinct()->orderBy('event')->pluck('event')->filter()->values()->all(),
        ];
    }
}
