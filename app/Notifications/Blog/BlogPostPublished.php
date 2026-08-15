<?php

namespace App\Notifications\Blog;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use App\Models\BlogPost;
use App\Notifications\BaseNotification;

class BlogPostPublished extends BaseNotification
{
    public function __construct(public BlogPost $post)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Blog;
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Success;
    }

    public function title(object $notifiable): string
    {
        return 'تم نشر مقال';
    }

    public function message(object $notifiable): string
    {
        $author = $this->post->author?->name;

        return "المقال «{$this->post->title}»".($author ? " بواسطة {$author}" : '').' أصبح منشوراً.';
    }

    public function actionUrl(object $notifiable): ?string
    {
        return route('admin.blog-posts.edit', $this->post->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'slug' => $this->post->slug,
        ];
    }
}
