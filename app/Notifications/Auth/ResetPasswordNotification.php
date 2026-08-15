<?php

namespace App\Notifications\Auth;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationLevel;
use App\Enums\WhatsAppPurpose;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset link, delivered over mail and WhatsApp.
 *
 * Two deliberate departures from the rest of the notification system:
 *
 *  - **No in-app or push record.** The link is a bearer credential: whoever holds it
 *    can take the account. Storing it in `notifications.data` would leave a working
 *    reset token sitting in a table that admin screens read. The user is also logged
 *    out when they need it, so an in-app copy helps nobody.
 *  - **Preferences are ignored.** A user who muted the "users" category would
 *    otherwise be unable to recover their own account.
 */
class ResetPasswordNotification extends BaseNotification
{
    public function __construct(public string $token)
    {
        parent::__construct();
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::Users;
    }

    public function level(): NotificationLevel
    {
        return NotificationLevel::Warning;
    }

    /**
     * @return array<int, NotificationChannel>
     */
    public function supportedChannels(): array
    {
        return [NotificationChannel::Mail, NotificationChannel::WhatsApp];
    }

    public function bypassesPreferences(): bool
    {
        return true;
    }

    public function title(object $notifiable): string
    {
        return 'إعادة تعيين كلمة المرور';
    }

    public function message(object $notifiable): string
    {
        return 'وصلنا طلب لإعادة تعيين كلمة مرور حسابك. الرابط صالح لمدة '
            .$this->expiryMinutes().' دقيقة، ويُستخدم مرة واحدة.';
    }

    public function actionUrl(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: true);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting($this->title($notifiable))
            ->line($this->message($notifiable))
            ->action('إعادة تعيين كلمة المرور', $this->actionUrl($notifiable))
            ->line('إن لم تطلب ذلك، تجاهل هذه الرسالة ولن يتغيّر شيء.')
            ->salutation(config('app.name'));
    }

    public function toWhatsApp(object $notifiable): string
    {
        return implode("\n\n", [
            '*'.$this->title($notifiable).'*',
            $this->message($notifiable),
            $this->actionUrl($notifiable),
            'إن لم تطلب ذلك، تجاهل هذه الرسالة.',
        ]);
    }

    /**
     * Meta templates take the URL's tail as the button/body parameter, not the whole
     * link — the domain is fixed in the approved template.
     *
     * @return array<int, string>
     */
    public function whatsAppTemplateParameters(object $notifiable): array
    {
        return [$this->token];
    }

    public function whatsAppPurpose(): WhatsAppPurpose
    {
        return WhatsAppPurpose::PasswordReset;
    }

    /**
     * Mirrors the lifetime Laravel's password broker actually enforces, so the
     * message never promises a window the backend has already closed.
     */
    protected function expiryMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
    }
}
