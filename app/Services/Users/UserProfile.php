<?php

namespace App\Services\Users;

use App\Models\CourseEnrollment;
use App\Models\LessonProgress;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Observability\SecurityLogger;
use App\Support\AccountType;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * Everything the admin "user details" page shows about one account: identity
 * and verification, courses with progress, request history, recent lessons,
 * newsletter status and (for those allowed) security events.
 */
class UserProfile
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $user, User $viewer): array
    {
        $enrollments = $user->enrollments()
            ->with([
                'course' => fn ($query) => $query->withTrashed()->with('category:id,name,color'),
                'decider:id,name',
                'lastLesson:id,title',
            ])
            ->latest('updated_at')
            ->get();

        $done = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->selectRaw('course_id, count(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id');

        $learnedSeconds = (int) LessonProgress::query()
            ->where('lesson_progress.user_id', $user->id)
            ->whereNotNull('lesson_progress.completed_at')
            ->join('course_lessons', 'course_lessons.id', '=', 'lesson_progress.course_lesson_id')
            ->sum('course_lessons.duration_seconds');

        $approved = $enrollments->filter->isApproved();

        return [
            'user' => $this->identity($user),
            'stats' => [
                'courses' => $approved->count(),
                'completed' => $approved->filter->isCompleted()->count(),
                'pending' => $enrollments->filter->isPending()->count(),
                'rejected' => $enrollments->filter->isRejected()->count(),
                'lessons' => (int) $done->sum(),
                'minutes' => (int) round($learnedSeconds / 60),
                'last_activity' => $approved->max('last_accessed_at')?->diffForHumans(),
            ],
            'courses' => $approved->values()->map(fn (CourseEnrollment $e) => [
                ...$this->courseOf($e),
                'progress' => $e->progress_percent,
                'done' => (int) ($done[$e->course_id] ?? 0),
                'total' => (int) ($e->course?->lessons_count ?? 0),
                'completed_at' => $e->completed_at?->format('Y-m-d'),
                'last_accessed' => $e->last_accessed_at?->diffForHumans(),
                'last_lesson' => $e->lastLesson?->title,
                'joined_at' => ($e->decided_at ?? $e->created_at)?->format('Y-m-d'),
            ])->all(),
            'requests' => $enrollments->map(fn (CourseEnrollment $e) => [
                'id' => $e->id,
                ...$this->courseOf($e),
                'status' => $e->status,
                'status_label' => $e->statusLabel(),
                'source' => $e->source === CourseEnrollment::SOURCE_ADMIN ? 'أضافته الإدارة' : 'طلب من الطالب',
                'message' => $e->message,
                'admin_note' => $e->admin_note,
                'decided_by' => $e->decider?->name,
                'created_at' => $e->created_at?->format('Y-m-d H:i'),
                'decided_at' => $e->decided_at?->format('Y-m-d H:i'),
            ])->all(),
            'recentLessons' => LessonProgress::query()
                ->where('user_id', $user->id)
                ->with(['lesson:id,title,course_id', 'course' => fn ($query) => $query->withTrashed()->select('id', 'title')])
                ->latest('updated_at')
                ->take(8)
                ->get()
                ->map(fn (LessonProgress $row) => [
                    'id' => $row->id,
                    'lesson' => $row->lesson?->title,
                    'course' => $row->course?->title,
                    'completed' => $row->isCompleted(),
                    'when' => $row->updated_at?->diffForHumans(),
                ])->all(),
            'newsletter' => $this->newsletter($user),
            'security' => $viewer->can('admin.observability.security') ? $this->security($user) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(User $user): array
    {
        return [
            'id' => $user->id,
            'name_ar' => $user->name_ar,
            'name_en' => $user->name_en,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'phone' => $user->phone_e164 ? PhoneNumber::format($user->phone_e164) : $user->phone,
            'phone_e164' => $user->phone_e164,
            'phone_verified' => $user->phone_verified_at !== null,
            'account_type' => AccountType::of($user),
            'roles' => $user->getRoleNames()->values()->all(),
            'created_at' => $user->created_at?->format('Y-m-d'),
            'created_human' => $user->created_at?->diffForHumans(),
            'last_login' => $user->last_login_at?->diffForHumans(),
            'last_login_at' => $user->last_login_at?->format('Y-m-d H:i'),
            'last_login_ip' => $user->last_login_ip,
        ];
    }

    /**
     * @return array{course_id: int, title: string, slug: string|null, category: string|null, color: string, thumbnail: string|null, deleted: bool}
     */
    private function courseOf(CourseEnrollment $enrollment): array
    {
        $course = $enrollment->course;

        return [
            'course_id' => $enrollment->course_id,
            'title' => $course?->title ?? 'دورة محذوفة',
            'slug' => $course?->slug,
            'category' => $course?->category?->name,
            'color' => $course?->category?->color ?: '#02ad8f',
            'thumbnail' => $course?->thumbnailUrl(),
            'deleted' => $course === null || $course->trashed(),
            // Ownership-aware: an instructor without edit-any may edit only their own courses.
            'can_edit' => $course !== null && ! $course->trashed() && Gate::allows('update', $course),
        ];
    }

    /**
     * @return array{status: string, label: string, since: string|null}|null
     */
    private function newsletter(User $user): ?array
    {
        $subscriber = NewsletterSubscriber::query()->where('email', $user->email)->first();

        if (! $subscriber) {
            return null;
        }

        $subscribed = $subscriber->unsubscribed_at === null;

        return [
            'status' => $subscribed ? 'subscribed' : 'unsubscribed',
            'label' => $subscribed ? 'مشترك في القائمة البريدية' : 'ألغى الاشتراك',
            'since' => ($subscribed ? $subscriber->subscribed_at ?? $subscriber->created_at : $subscriber->unsubscribed_at)?->format('Y-m-d'),
        ];
    }

    /**
     * Security events about this account: its own sign-ins, and admins acting on it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function security(User $user): array
    {
        return Activity::query()
            ->where('log_name', SecurityLogger::LOG_NAME)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                ->orWhere('properties->student_id', $user->id))
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'ip' => $activity->getExtraProperty('ip'),
                'when' => $activity->created_at?->diffForHumans(),
                'at' => $activity->created_at?->format('Y-m-d H:i'),
            ])
            ->all();
    }
}
