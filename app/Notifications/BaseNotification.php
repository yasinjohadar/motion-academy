<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationLevel;
use App\Support\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base for every application notification.
 *
 * A concrete notification only has to declare its category, title and message —
 * the database row, broadcast payload, mail body and WhatsApp text are all derived
 * from those. Override any of the `to*` methods when a channel needs a richer form.
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notifications are frequently dispatched inside transactions (backup runs,
     * user creation), so never let the worker pick the job up before the commit.
     */
    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function category(): NotificationCategory;

    abstract public function title(object $notifiable): string;

    abstract public function message(object $notifiable): string;

    public function level(): NotificationLevel
    {
        return NotificationLevel::Info;
    }

    /**
     * Where clicking the notification takes the user. Use `route()`.
     */
    public function actionUrl(object $notifiable): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return $this->category()->icon();
    }

    /**
     * Channels this notification type is allowed to use. Narrow this in a subclass
     * to keep a notification off a channel — e.g. a "WhatsApp message received"
     * notification must not be delivered over WhatsApp.
     *
     * @return array<int, NotificationChannel>
     */
    public function supportedChannels(): array
    {
        return NotificationChannel::cases();
    }

    /**
     * Extra data merged into the stored `data` column and the broadcast payload.
     *
     * @return array<string, mixed>
     */
    public function payload(object $notifiable): array
    {
        return [];
    }

    /**
     * Whether this notification ignores the user's channel preferences.
     *
     * Only for transactional security messages — a password reset or an OTP that a
     * preference toggle could suppress would lock the user out of their own account.
     * Availability and `supportedChannels()` still apply.
     */
    public function bypassesPreferences(): bool
    {
        return false;
    }

    /**
     * Extra instructions for one delivery channel, keyed by channel value.
     *
     * This replaced a pair of WhatsApp-typed methods on this class. A base class every
     * notification extends must not carry one module's vocabulary — it made the whole
     * notification system undeliverable without that module.
     *
     * Keys are plain strings on purpose: a core notification states its intent, and the
     * module that owns the channel decides what that intent means. The WhatsApp channel,
     * for instance, reads `purpose` and `template_parameters`.
     *
     * @return array<string, mixed>
     */
    public function channelOptions(string $channel, object $notifiable): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    final public function via(object $notifiable): array
    {
        return app(NotificationChannelResolver::class)->resolve($notifiable, $this);
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        $queues = [];

        foreach (NotificationChannel::cases() as $channel) {
            $queues[$channel->value] = $channel->queue();
        }

        return $queues;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'message' => $this->message($notifiable),
            'icon' => $this->icon(),
            'category' => $this->category()->value,
            'level' => $this->level()->value,
            'action_url' => $this->actionUrl($notifiable),
            ...$this->payload($notifiable),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'category_label' => $this->category()->label(),
            'level_badge' => $this->level()->badgeClass(),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting($this->title($notifiable))
            ->line($this->message($notifiable));

        if ($url = $this->actionUrl($notifiable)) {
            $mail->action('عرض التفاصيل', $url);
        }

        return $mail->salutation(config('app.name'));
    }

    /**
     * Plain-text body for the WhatsApp channel.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $lines = [
            '*'.$this->title($notifiable).'*',
            $this->message($notifiable),
        ];

        if ($url = $this->actionUrl($notifiable)) {
            $lines[] = $url;
        }

        return implode("\n\n", array_filter($lines));
    }

    public function toWebPush(object $notifiable, ?Notification $notification = null): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title($notifiable))
            ->body($this->message($notifiable))
            ->tag($this->category()->value)
            ->options(['TTL' => 60 * 60 * 24]);

        // Read by public/sw.js on notificationclick to open the right page.
        if ($url = $this->actionUrl($notifiable)) {
            $message->data(['url' => $url]);
        }

        return $message;
    }
}
