<?php

namespace App\Models;

use App\Enums\LessonType;
use App\Enums\VideoSource;
use App\Services\Storage\AppStorageManager;
use Database\Factories\CourseLessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enum casts are invisible to static analysis without these.
 *
 * @property LessonType $type
 * @property VideoSource|null $video_source
 */
class CourseLesson extends Model
{
    /** @use HasFactory<CourseLessonFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id', 'course_section_id', 'title', 'type',
        'video_source', 'video', 'content', 'attachment',
        'duration_seconds', 'is_preview', 'is_published', 'order',
    ];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'video_source' => VideoSource::class,
            'duration_seconds' => 'integer',
            'is_preview' => 'boolean',
            'is_published' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lesson) {
            // course_id is a denormalised copy of the section's course.
            if ($lesson->course_section_id && $lesson->isDirty('course_section_id')) {
                $lesson->course_id = (int) CourseSection::query()->whereKey($lesson->course_section_id)->value('course_id');
            }
        });

        static::saved(fn (self $lesson) => $lesson->course?->refreshStats());
        static::deleted(fn (self $lesson) => $lesson->course?->refreshStats());
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CourseLessonAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(CourseLessonAttachment::class)->orderBy('order')->orderBy('id');
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function hasStoredVideo(): bool
    {
        return $this->video_source === VideoSource::Upload && filled($this->video);
    }

    public function hasStoredAttachment(): bool
    {
        return Course::isStoredPath($this->attachment);
    }

    /**
     * Playable URL for the admin preview: embed for YouTube/Vimeo, signed link for uploads.
     */
    public function videoUrl(): ?string
    {
        if (! filled($this->video) || $this->video_source === null) {
            return null;
        }

        return match ($this->video_source) {
            VideoSource::Upload => app(AppStorageManager::class)->temporaryUrl(Course::VIDEO_DISK, $this->video, 120),
            VideoSource::Youtube, VideoSource::Vimeo => $this->video_source->embedUrl($this->video),
            VideoSource::Url => $this->video,
        };
    }

    public function attachmentUrl(): ?string
    {
        if (! filled($this->attachment)) {
            return null;
        }

        return $this->hasStoredAttachment()
            ? app(AppStorageManager::class)->temporaryUrl(Course::VIDEO_DISK, $this->attachment, 120)
            : $this->attachment;
    }

    public function formattedDuration(): ?string
    {
        return $this->duration_seconds > 0
            ? sprintf('%d:%02d', intdiv($this->duration_seconds, 60), $this->duration_seconds % 60)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'course_section_id' => $this->course_section_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'video_source' => $this->video_source?->value,
            'video' => $this->hasStoredVideo() ? null : $this->video,
            'video_file_name' => $this->hasStoredVideo() ? basename((string) $this->video) : null,
            'video_url' => $this->videoUrl(),
            'content' => $this->content,
            'attachment' => $this->hasStoredAttachment() ? null : $this->attachment,
            'attachment_name' => $this->hasStoredAttachment() ? basename((string) $this->attachment) : null,
            'attachment_url' => $this->attachmentUrl(),
            'attachments' => $this->relationLoaded('attachments')
                ? $this->attachments->map->toAdminArray()->values()->all()
                : [],
            'duration_seconds' => $this->duration_seconds,
            'is_preview' => $this->is_preview,
            'is_published' => $this->is_published,
            'order' => $this->order,
        ];
    }
}
