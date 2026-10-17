<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Notifications\Auth\ResetPasswordNotification;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * Casts are invisible to static analysis, so every Carbon call on these reads
 * as a method call on a string.
 *
 * @property CarbonInterface|null $email_verified_at
 * @property CarbonInterface|null $last_login_at
 * @property CarbonInterface|null $phone_verified_at
 */
#[Fillable(['name', 'name_ar', 'name_en', 'email', 'phone', 'phone_e164', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use AuditsChanges, CausesActivity, HasFactory, HasPushSubscriptions, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Keeps queued notifications in Arabic — the app's UI locale — regardless of the
     * locale the worker happens to boot with.
     */
    public function preferredLocale(): string
    {
        return config('app.locale', 'ar');
    }

    /**
     * Route notifications for the WhatsApp channel.
     */
    public function routeNotificationForWhatsapp(?Notification $notification = null): ?string
    {
        // The canonical number is what WhatsApp can actually deliver to; the free-form
        // `phone` is only a fallback for accounts that have not been normalised.
        return $this->phone_e164 ?: (filled($this->phone) ? (string) $this->phone : null);
    }

    /**
     * Send the reset link over every channel we can reach the user on, instead of
     * Laravel's mail-only default. A user with a phone but a dead mailbox — or the
     * reverse — can still recover the account.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** @return HasMany<NotificationPreference, $this> */
    /** @return HasMany<CourseEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (filled($user->name_ar)) {
                $user->name = $user->name_ar;
            } elseif (filled($user->name_en)) {
                $user->name = $user->name_en;
            }
        });
    }
}
