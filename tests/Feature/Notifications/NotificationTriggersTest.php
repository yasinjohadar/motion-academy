<?php

namespace Tests\Feature\Notifications;

use App\Events\WhatsAppMessageReceived;
use App\Listeners\NotifyOnWhatsAppMessageReceived;
use App\Models\BlogPost;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Notifications\Blog\BlogPostPublished;
use App\Notifications\Users\UserCreated;
use App\Notifications\Users\UserRoleChanged;
use App\Notifications\WhatsApp\WhatsAppMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'admin.users.list',
            'admin.users.create',
            'admin.users.edit',
            'admin.users.delete',
            'admin.blog-posts.list',
            'admin.blog-posts.create',
            'admin.blog-posts.edit',
            'admin.blog-posts.delete',
            'admin.whatsapp-messages.list',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(Permission::all());
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_creating_a_user_notifies_admins(): void
    {
        Notification::fake();

        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name_ar' => 'مستخدم جديد',
            'name_en' => 'New User',
            'email' => 'new-user@example.com',
            'phone' => '966500000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => [],
        ])->assertRedirect(route('admin.users.index'));

        Notification::assertSentTo($admin, UserCreated::class);
    }

    public function test_changing_roles_notifies_the_affected_user(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target->id), [
            'name_ar' => $target->name_ar ?? 'مستخدم',
            'name_en' => $target->name_en ?? 'User',
            'email' => $target->email,
            'phone' => $target->phone,
            'roles' => ['admin'],
        ])->assertRedirect(route('admin.users.index'));

        Notification::assertSentTo($target, UserRoleChanged::class);
    }

    public function test_no_role_notification_when_roles_are_unchanged(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $target = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target->id), [
            'name_ar' => 'اسم محدّث',
            'name_en' => 'Updated Name',
            'email' => $target->email,
            'phone' => $target->phone,
            'roles' => [],
        ]);

        Notification::assertNothingSentTo($target);
    }

    public function test_publishing_a_post_notifies_blog_users(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $post = BlogPost::query()->create([
            'title' => 'مقال تجريبي',
            'slug' => 'test-post',
            'content' => 'محتوى المقال.',
            'status' => 'draft',
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.blog-posts.toggle-publish', $post->id))
            ->assertRedirect();

        Notification::assertSentTo($admin, BlogPostPublished::class);
    }

    public function test_unpublishing_a_post_sends_nothing(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $post = BlogPost::query()->create([
            'title' => 'مقال منشور',
            'slug' => 'published-post',
            'content' => 'محتوى المقال.',
            'status' => 'published',
            'published_at' => now(),
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.blog-posts.toggle-publish', $post->id));

        Notification::assertNothingSent();
    }

    public function test_inbound_whatsapp_listener_notifies_once_per_contact_per_minute(): void
    {
        Notification::fake();

        $admin = $this->adminUser();
        $contact = WhatsAppContact::findOrCreateByWaId('966500000000');

        $listener = app(NotifyOnWhatsAppMessageReceived::class);

        foreach (['أول', 'ثاني', 'ثالث'] as $body) {
            $message = WhatsAppMessage::query()->create([
                'direction' => WhatsAppMessage::DIRECTION_INBOUND,
                'contact_id' => $contact->id,
                'type' => WhatsAppMessage::TYPE_TEXT,
                'body' => $body,
                'status' => WhatsAppMessage::STATUS_DELIVERED,
            ]);

            $listener->handle(new WhatsAppMessageReceived($message));
        }

        // Rate limited to one notification per contact per minute.
        Notification::assertSentToTimes($admin, WhatsAppMessageReceivedNotification::class, 1);
    }

    public function test_broadcast_event_is_dispatched_when_realtime_is_enabled(): void
    {
        config()->set('notifications.realtime.enabled', true);
        config()->set('broadcasting.default', 'reverb');

        Event::fake([BroadcastNotificationCreated::class]);

        $user = User::factory()->create();
        $post = BlogPost::query()->create([
            'title' => 'مقال',
            'slug' => 'broadcast-post',
            'content' => 'محتوى المقال.',
            'status' => 'published',
            'published_at' => now(),
            'author_id' => $user->id,
        ]);

        $user->notify(new BlogPostPublished($post));

        Event::assertDispatched(BroadcastNotificationCreated::class);
    }

    public function test_no_broadcast_event_when_realtime_is_disabled(): void
    {
        config()->set('notifications.realtime.enabled', false);

        Event::fake([BroadcastNotificationCreated::class]);

        $user = User::factory()->create();
        $post = BlogPost::query()->create([
            'title' => 'مقال',
            'slug' => 'no-broadcast-post',
            'content' => 'محتوى المقال.',
            'status' => 'published',
            'published_at' => now(),
            'author_id' => $user->id,
        ]);

        $user->notify(new BlogPostPublished($post));

        Event::assertNotDispatched(BroadcastNotificationCreated::class);
    }
}
