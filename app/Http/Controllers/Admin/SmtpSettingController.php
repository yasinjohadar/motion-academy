<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmtpSettingProbeRequest;
use App\Http\Requests\Admin\SmtpSettingStoreRequest;
use App\Http\Requests\Admin\SmtpSettingTestRequest;
use App\Http\Requests\Admin\SmtpSettingUpdateRequest;
use App\Models\SmtpSetting;
use App\Services\Smtp\SmtpConfigApplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SmtpSettingController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'smtp-settings',
            false,
            [
                'admin.smtp-settings.test' => ['test', 'testDraft', 'setDefault', 'toggleActive'],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $settings = SmtpSetting::query()
            ->latest('id')
            ->get()
            ->map(fn (SmtpSetting $setting) => $setting->toAdminArray());

        return Inertia::render('Admin/SmtpSettings/Index', [
            'settings' => $settings,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/SmtpSettings/Create', [
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(SmtpSettingStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $setting = SmtpSetting::create([
            ...$data,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($setting->is_default) {
            $setting->setAsDefault();
        }

        SmtpConfigApplier::refresh();

        return redirect()
            ->route('admin.smtp-settings.index')
            ->with('success', 'تم إنشاء إعداد SMTP بنجاح.');
    }

    public function edit(Request $request, SmtpSetting $smtp_setting): Response
    {
        return Inertia::render('Admin/SmtpSettings/Edit', [
            'setting' => $smtp_setting->toAdminArray(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(SmtpSettingUpdateRequest $request, SmtpSetting $smtp_setting): RedirectResponse
    {
        $data = $request->validated();

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $makeDefault = (bool) ($data['is_default'] ?? $smtp_setting->is_default);
        $isActive = (bool) ($data['is_active'] ?? $smtp_setting->is_active);

        if ($smtp_setting->is_default && ! $isActive) {
            return back()->with('error', 'لا يمكن تعطيل الإعداد الافتراضي. عيّن إعداداً افتراضياً آخر أولاً.');
        }

        $smtp_setting->fill([
            ...$data,
            'is_active' => $isActive,
            'is_default' => $makeDefault,
        ])->save();

        if ($makeDefault) {
            $smtp_setting->setAsDefault();
        }

        SmtpConfigApplier::refresh();

        return redirect()
            ->route('admin.smtp-settings.index')
            ->with('success', 'تم تحديث إعداد SMTP بنجاح.');
    }

    public function destroy(SmtpSetting $smtp_setting): RedirectResponse
    {
        if ($smtp_setting->is_default) {
            return back()->with('error', 'لا يمكن حذف الإعداد الافتراضي. عيّن إعداداً آخر كافتراضي أولاً.');
        }

        $smtp_setting->delete();
        SmtpConfigApplier::refresh();

        return redirect()
            ->route('admin.smtp-settings.index')
            ->with('success', 'تم حذف إعداد SMTP بنجاح.');
    }

    public function setDefault(SmtpSetting $smtp_setting): RedirectResponse
    {
        $smtp_setting->setAsDefault();
        SmtpConfigApplier::refresh();

        return back()->with('success', 'تم تعيين الإعداد كافتراضي.');
    }

    public function toggleActive(SmtpSetting $smtp_setting): RedirectResponse
    {
        if ($smtp_setting->is_default && $smtp_setting->is_active) {
            return back()->with('error', 'لا يمكن تعطيل الإعداد الافتراضي. عيّن إعداداً افتراضياً آخر أولاً.');
        }

        $smtp_setting->update(['is_active' => ! $smtp_setting->is_active]);
        SmtpConfigApplier::refresh();

        return back()->with(
            'success',
            $smtp_setting->is_active ? 'تم تفعيل الإعداد.' : 'تم تعطيل الإعداد.',
        );
    }

    public function test(SmtpSettingTestRequest $request, SmtpSetting $smtp_setting): RedirectResponse
    {
        $data = $request->validated();
        $password = filled($data['password'] ?? null)
            ? $data['password']
            : $smtp_setting->password;

        if (! filled($password)) {
            return back()->with('error', 'كلمة مرور SMTP مطلوبة لإرسال اختبار.');
        }

        return $this->sendProbe(
            to: $data['to'],
            name: $smtp_setting->name,
            host: $smtp_setting->host,
            port: (int) $smtp_setting->port,
            username: $smtp_setting->username,
            password: $password,
            encryption: $smtp_setting->encryption,
            fromAddress: $smtp_setting->from_address,
            fromName: $smtp_setting->from_name,
        );
    }

    public function testDraft(SmtpSettingProbeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->sendProbe(
            to: $data['to'],
            name: $data['name'] ?: 'إعداد تجريبي',
            host: $data['host'],
            port: (int) $data['port'],
            username: $data['username'],
            password: $data['password'],
            encryption: $data['encryption'],
            fromAddress: $data['from_address'],
            fromName: $data['from_name'],
        );
    }

    protected function sendProbe(
        string $to,
        string $name,
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption,
        string $fromAddress,
        string $fromName,
    ): RedirectResponse {
        $temp = new SmtpSetting([
            'name' => $name,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ]);

        config([
            'mail.mailers.smtp_test' => array_merge(
                config('mail.mailers.smtp', []),
                $temp->toMailerConfig(),
            ),
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        try {
            app('mail.manager')->purge('smtp_test');

            Mail::mailer('smtp_test')->raw(
                'هذه رسالة اختبار SMTP من لوحة التحكم.',
                function ($message) use ($to, $name, $fromAddress, $fromName) {
                    $message
                        ->to($to)
                        ->subject('اختبار اتصال SMTP — '.$name)
                        ->from($fromAddress, $fromName);
                },
            );

            return back()->with('success', 'تم إرسال بريد الاختبار بنجاح إلى '.$to);
        } catch (Throwable $e) {
            return back()->with('error', 'فشل إرسال الاختبار: '.$e->getMessage());
        }
    }
}
