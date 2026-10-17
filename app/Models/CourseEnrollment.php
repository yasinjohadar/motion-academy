<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CourseEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $decided_at
 * @property CarbonInterface|null $last_accessed_at
 * @property CarbonInterface|null $completed_at
 */
class CourseEnrollment extends Model
{
    /** @use HasFactory<CourseEnrollmentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'بانتظار الموافقة',
        self::STATUS_APPROVED => 'منضم',
        self::STATUS_REJECTED => 'مرفوض',
    ];

    public const SOURCE_REQUEST = 'request';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'course_id', 'user_id', 'status', 'source', 'message', 'admin_note', 'decided_by', 'decided_at',
        'last_lesson_id', 'last_accessed_at', 'progress_percent', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress_percent' => 'integer',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CourseLesson, $this> */
    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'last_lesson_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function approve(?User $by = null, ?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'decided_by' => $by?->id,
            'decided_at' => now(),
            'admin_note' => $note,
        ])->save();
    }

    public function reject(?User $by = null, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'decided_by' => $by?->id,
            'decided_at' => now(),
            'admin_note' => $reason,
        ])->save();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'source' => $this->source,
            'message' => $this->message,
            'admin_note' => $this->admin_note,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'decided_at' => $this->decided_at?->format('Y-m-d H:i'),
            'decider' => $this->decider?->name,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
                'thumbnail_url' => $this->course->thumbnailUrl(),
            ] : null,
        ];
    }
}
