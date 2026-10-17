import OtpInput from '@/Components/Auth/OtpInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Shared "enter the code" screen for WhatsApp sign-in and password reset.
 */
export default function OtpVerify({
    purpose,
    title,
    maskedPhone,
    length,
    resendIn,
    submitUrl,
    resendUrl,
    changeUrl,
    status,
}) {
    const isLogin = purpose === 'login';
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        code: '',
        remember: false,
    });
    const [seconds, setSeconds] = useState(resendIn);
    const [resending, setResending] = useState(false);

    // The server sends a fresh cooldown after every resend.
    const [syncedResendIn, setSyncedResendIn] = useState(resendIn);
    if (resendIn !== syncedResendIn) {
        setSyncedResendIn(resendIn);
        setSeconds(resendIn);
    }

    useEffect(() => {
        if (seconds <= 0) return undefined;
        const timer = setTimeout(() => setSeconds((s) => s - 1), 1000);
        return () => clearTimeout(timer);
    }, [seconds]);

    const submit = (code = data.code) => {
        if (processing || code.length < length) return;
        // Auto-submit runs before the last digit reaches form state; send the code we were given.
        transform((current) => ({ ...current, code }));
        post(submitUrl, {
            preserveScroll: true,
            onError: () => reset('code'),
        });
    };

    const resend = () => {
        if (seconds > 0 || resending) return;
        setResending(true);
        router.post(resendUrl, {}, { preserveScroll: true, onFinish: () => setResending(false) });
    };

    const mm = String(Math.floor(seconds / 60)).padStart(1, '0');
    const ss = String(seconds % 60).padStart(2, '0');

    return (
        <GuestLayout
            title={title}
            subtitle="أرسلنا رمزاً على واتساب إلى الرقم أدناه"
            icon={isLogin ? 'ri-shield-keyhole-line' : 'ri-key-2-line'}
            aside={{
                heading: isLogin ? 'خطوة واحدة وتدخل' : 'تحقق سريع ثم كلمة مرور جديدة',
                text: 'لا تشارك هذا الرمز مع أي أحد — فريقنا لن يطلبه منك أبداً.',
            }}
        >
            <Head title={title} />

            {!isLogin && (
                <ol className="auth-steps" aria-label="الخطوات">
                    <li className="is-done">
                        <span>
                            <i className="ri-check-line" />
                        </span>{' '}
                        الرقم
                    </li>
                    <li className="is-current">
                        <span>2</span> الرمز
                    </li>
                    <li>
                        <span>3</span> كلمة مرور جديدة
                    </li>
                </ol>
            )}

            <div className="otp-target">
                <span className="otp-target-icon">
                    <i className="ri-whatsapp-line" />
                </span>
                <div>
                    <small>إن كان هذا الرقم مسجّلاً لدينا فقد وصله الرمز</small>
                    <strong dir="ltr">{maskedPhone}</strong>
                </div>
                <Link href={changeUrl} className="otp-change">
                    تغيير
                </Link>
            </div>

            {status && (
                <div className="auth-alert is-success" role="status">
                    <i className="ri-checkbox-circle-line" />
                    <span>{status}</span>
                </div>
            )}

            <form
                className="auth-form"
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
            >
                <div>
                    <OtpInput
                        length={length}
                        value={data.code}
                        onChange={(code) => setData('code', code)}
                        onComplete={(code) => submit(code)}
                        error={errors.code}
                        disabled={processing}
                    />
                    {errors.code && (
                        <p className="auth-error text-center" role="alert">
                            <i className="ri-error-warning-line" /> {errors.code}
                        </p>
                    )}
                </div>

                {isLogin && (
                    <label className="auth-check">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="auth-check-box" aria-hidden="true">
                            <i className="ri-check-line" />
                        </span>
                        تذكّرني على هذا الجهاز
                    </label>
                )}

                <button
                    type="submit"
                    className="auth-submit"
                    disabled={processing || data.code.length < length}
                >
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-shield-check-line" />
                    )}
                    {processing ? 'جارٍ التحقق...' : isLogin ? 'تحقّق وادخل' : 'تحقّق وتابع'}
                </button>
            </form>

            <div className="otp-resend">
                {seconds > 0 ? (
                    <span>
                        يمكنك طلب رمز جديد بعد{' '}
                        <strong className="en-text" dir="ltr">
                            {mm}:{ss}
                        </strong>
                    </span>
                ) : (
                    <button type="button" onClick={resend} disabled={resending}>
                        <i className={resending ? 'ri-loader-4-line' : 'ri-refresh-line'} /> لم يصلك الرمز؟
                        أعد الإرسال
                    </button>
                )}
            </div>

            <Link href={isLogin ? route('login') : route('password.request')} className="auth-back-link">
                <i className="ri-arrow-right-line" /> {isLogin ? 'الدخول بالبريد وكلمة المرور' : 'العودة'}
            </Link>
        </GuestLayout>
    );
}
