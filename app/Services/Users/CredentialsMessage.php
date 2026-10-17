<?php

namespace App\Services\Users;

use App\Models\SiteSetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhoneNumber;

/**
 * The WhatsApp message that hands a student their sign-in details after an
 * admin sets their password. Written with the same {placeholder} style as the
 * WhatsApp campaigns; the admin's last saved text becomes the default.
 */
class CredentialsMessage
{
    private const GROUP = 'credentials';

    private const KEY = 'whatsapp_template';

    public const PLACEHOLDERS = [
        '{name}' => 'اسم الطالب',
        '{email}' => 'البريد (اسم الدخول)',
        '{phone}' => 'رقم الهاتف',
        '{password}' => 'كلمة المرور',
        '{login_url}' => 'رابط الدخول',
        '{site}' => 'اسم الموقع',
    ];

    /** @var array<string, array{label: string, text: string}> */
    public const PRESETS = [
        'formal' => [
            'label' => 'رسمي',
            'text' => "مرحباً {name}،\n\nتم تحديث بيانات الدخول إلى حسابك في {site}:\n\n📧 البريد: {email}\n🔑 كلمة المرور: {password}\n\nرابط الدخول: {login_url}\n\nننصحك بتغيير كلمة المرور بعد أول دخول، ولا تشاركها مع أحد.",
        ],
        'friendly' => [
            'label' => 'ودّي',
            'text' => "أهلاً {name} 👋\n\nجهّزنا لك حسابك في {site} — يمكنك البدء الآن!\n\nالبريد: {email}\nكلمة المرور: {password}\n\nادخل من هنا 👇\n{login_url}\n\nبالتوفيق في رحلتك التعليمية 🎬",
        ],
        'short' => [
            'label' => 'مختصر',
            'text' => "{site}\nالبريد: {email}\nكلمة المرور: {password}\n{login_url}",
        ],
    ];

    public function template(): string
    {
        // The whole row, not ->value('value'): the model's value accessor reads `type`.
        $saved = SystemSetting::query()
            ->where('group', self::GROUP)
            ->where('key', self::KEY)
            ->first()?->value;

        return filled($saved) ? (string) $saved : self::PRESETS['formal']['text'];
    }

    public function saveTemplate(string $text): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY, 'group' => self::GROUP],
            ['type' => 'string', 'value' => $text],
        );
    }

    public function render(string $template, User $user, string $password): string
    {
        return strtr($template, [
            '{name}' => (string) ($user->name_ar ?: $user->name),
            '{email}' => (string) $user->email,
            '{phone}' => $user->phone_e164 ? PhoneNumber::format($user->phone_e164) : (string) $user->phone,
            '{password}' => $password,
            '{login_url}' => route('login'),
            '{site}' => $this->siteName(),
        ]);
    }

    public function siteName(): string
    {
        try {
            return (string) (SiteSetting::current()->site_name ?: config('app.name'));
        } catch (\Throwable) {
            return (string) config('app.name');
        }
    }
}
