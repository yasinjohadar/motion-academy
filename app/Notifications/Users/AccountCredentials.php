<?php

namespace App\Notifications\Users;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Notifications\BaseNotification;

/**
 * A student's sign-in details, sent over WhatsApp after an admin set their
 * password.
 *
 * Like the password reset link, the message carries a credential, so:
 *  - WhatsApp only — no in-app record that admin screens could read;
 *  - preferences are ignored (it is the student's way into their account);
 *  - the WhatsApp channel is asked to blank the password out of its stored copy
 *    of the message (`redact`), so the message log never shows it;
 *  - it is sent with notifyNow(), so it never waits serialised in the jobs table.
 */
class AccountCredentials extends BaseNotification
{
    public function __construct(
        private readonly string $text,
        private readonly string $password,
    ) {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Users;
    }

    /**
     * @return array<int, NotificationChannel>
     */
    public function supportedChannels(): array
    {
        return [NotificationChannel::WhatsApp];
    }

    public function bypassesPreferences(): bool
    {
        return true;
    }

    public function title(object $notifiable): string
    {
        return 'بيانات الدخول إلى حسابك';
    }

    public function message(object $notifiable): string
    {
        return 'أرسلت الإدارة بيانات الدخول إلى حسابك.';
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function channelOptions(string $channel, object $notifiable): array
    {
        return $channel === 'whatsapp'
            ? ['purpose' => 'system', 'redact' => [$this->password]]
            : [];
    }
}
