<?php

namespace App\Models;

use App\Services\Storage\AppStorageManager;
use Database\Factories\CourseLessonAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One resource under a lesson: a named link, or an uploaded file kept on the
 * private course disk and served through a short-lived URL.
 */
class CourseLessonAttachment extends Model
{
    /** @use HasFactory<CourseLessonAttachmentFactory> */
    use HasFactory;

    public const TYPE_LINK = 'link';

    public const TYPE_FILE = 'file';

    protected $fillable = [
        'course_lesson_id', 'type', 'title', 'url', 'path', 'original_name', 'size', 'order',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<CourseLesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function href(): ?string
    {
        if (! $this->isFile()) {
            return $this->url;
        }

        return filled($this->path)
            ? app(AppStorageManager::class)->temporaryUrl(Course::VIDEO_DISK, (string) $this->path, 120)
            : null;
    }

    public function extension(): ?string
    {
        $name = $this->original_name ?: $this->path;

        return filled($name) ? strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)) ?: null : null;
    }

    /**
     * "2.4 MB" for display.
     */
    public function humanSize(): ?string
    {
        if (! $this->size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    /**
     * Remix icon for the attachment's kind.
     */
    public function icon(): string
    {
        if (! $this->isFile()) {
            return match (true) {
                str_contains((string) $this->url, 'drive.google') => 'ri-drive-line',
                str_contains((string) $this->url, 'github.com') => 'ri-github-line',
                str_contains((string) $this->url, 'figma.com') => 'ri-pencil-ruler-2-line',
                str_contains((string) $this->url, 'youtube.com'), str_contains((string) $this->url, 'youtu.be') => 'ri-youtube-line',
                default => 'ri-link',
            };
        }

        return match ($this->extension()) {
            'pdf' => 'ri-file-pdf-2-line',
            'zip', 'rar', '7z' => 'ri-file-zip-line',
            'doc', 'docx' => 'ri-file-word-2-line',
            'xls', 'xlsx', 'csv' => 'ri-file-excel-2-line',
            'ppt', 'pptx' => 'ri-file-ppt-2-line',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => 'ri-image-line',
            'mp3', 'wav' => 'ri-music-2-line',
            'mp4', 'mov', 'webm' => 'ri-film-line',
            default => 'ri-file-3-line',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->isFile() ? null : $this->url,
            'file_name' => $this->original_name,
            'size' => $this->humanSize(),
            'href' => $this->href(),
            'icon' => $this->icon(),
        ];
    }
}
