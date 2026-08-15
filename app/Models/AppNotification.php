<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Notifications\Channels\AppDatabaseChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Read model over the `notifications` table.
 *
 * Extends Laravel's DatabaseNotification so `markAsRead()` and friends keep working,
 * and adds the extra indexed columns written by
 * {@see AppDatabaseChannel}.
 */
class AppNotification extends DatabaseNotification
{
    use HasFactory;

    protected $table = 'notifications';

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereMorphedTo('notifiable', $user);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $query->when(
            $category && NotificationCategory::tryFrom($category),
            fn (Builder $builder) => $builder->where('category', $category)
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLevel(Builder $query, ?string $level): Builder
    {
        return $query->when(
            $level && NotificationLevel::tryFrom($level),
            fn (Builder $builder) => $builder->where('level', $level)
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReadState(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => $query,
        };
    }

    public function categoryEnum(): ?NotificationCategory
    {
        return NotificationCategory::tryFrom((string) $this->category);
    }

    public function levelEnum(): NotificationLevel
    {
        return NotificationLevel::tryFrom((string) $this->level) ?? NotificationLevel::Info;
    }

    /**
     * Payload consumed by the notification bell and the notification centre page.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        $category = $this->categoryEnum();
        $level = $this->levelEnum();
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'action_url' => $this->action_url,
            'icon' => $data['icon'] ?? $category?->icon() ?? 'ri-notification-3-line',
            'category' => $this->category,
            'category_label' => $category?->label(),
            'category_badge' => $category?->badgeClass(),
            'level' => $level->value,
            'level_label' => $level->label(),
            'level_badge' => $level->badgeClass(),
            'is_read' => $this->read_at !== null,
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i'),
            'created_at_human' => optional($this->created_at)?->diffForHumans(),
        ];
    }
}
