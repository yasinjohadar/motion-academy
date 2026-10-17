<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's progress through one lesson. Written only through
 * App\Services\Learning\LearningProgress.
 *
 * @property CarbonInterface|null $completed_at
 */
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id', 'course_id', 'course_lesson_id', 'position_seconds', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
