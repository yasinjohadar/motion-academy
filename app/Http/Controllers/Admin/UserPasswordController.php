<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Users\AccountCredentials;
use App\Services\Observability\SecurityLogger;
use App\Services\Users\CredentialsMessage;
use App\Support\AccountType;
use App\Support\NotificationChannelResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * An admin sets a student's password from the user details page and, if asked,
 * sends the sign-in details to the student over WhatsApp.
 */
class UserPasswordController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CredentialsMessage $credentials,
        private readonly SecurityLogger $security,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:admin.users.edit')];
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if (AccountType::isAdmin($user)) {
            throw ValidationException::withMessages([
                'password' => 'كلمات مرور حسابات الأدمن تُغيَّر من صفحة التعديل أو من الحساب نفسه.',
            ]);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'max:128', Password::min(8)],
            'send_whatsapp' => ['boolean'],
            'message' => ['nullable', 'required_if:send_whatsapp,true', 'string', 'max:2000'],
            'save_template' => ['boolean'],
        ], [
            'message.required_if' => 'اكتب نص الرسالة أو أوقف الإرسال عبر واتساب.',
        ], [
            'password' => 'كلمة المرور',
            'message' => 'نص الرسالة',
        ]);

        $sending = $request->boolean('send_whatsapp');

        if ($sending && ! str_contains((string) $data['message'], '{password}')) {
            throw ValidationException::withMessages(['message' => 'أضف {password} إلى نص الرسالة حتى تصل كلمة المرور للطالب.']);
        }

        $user->forceFill([
            'password' => $data['password'],
            // Signs the student out of "remember me" on other devices.
            'remember_token' => Str::random(60),
        ])->save();

        $this->security->record(
            event: 'password_set_by_admin',
            description: "تعيين كلمة مرور جديدة للطالب {$user->email} من الإدارة",
            user: $request->user(),
            properties: ['student_id' => $user->id, 'student_email' => $user->email, 'sent_whatsapp' => $sending],
        );

        if ($request->boolean('save_template') && filled($data['message'] ?? null)) {
            $this->credentials->saveTemplate((string) $data['message']);
        }

        if (! $sending) {
            return back()->with('success', 'تم تغيير كلمة مرور '.$user->name.'.');
        }

        if (! app(NotificationChannelResolver::class)->isAvailable(NotificationChannel::WhatsApp, $user)) {
            return back()->with('warning', 'تم تغيير كلمة المرور، لكن لم تُرسل الرسالة: واتساب غير متاح لهذا الطالب (لا رقم أو لا يوجد رقم واتساب متصل).');
        }

        // notifyNow: the password must not wait serialised in the jobs table.
        $user->notifyNow(new AccountCredentials(
            $this->credentials->render((string) $data['message'], $user, $data['password']),
            $data['password'],
        ));

        return back()->with('success', 'تم تغيير كلمة المرور وإرسال بيانات الدخول إلى '.$user->name.' عبر واتساب.');
    }
}
