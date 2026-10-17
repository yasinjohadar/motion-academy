import AuthField from '@/Components/Auth/AuthField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, devQuickLogin, otpLoginAvailable }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const fillAdmin = () => {
        if (!devQuickLogin) {
            return;
        }
        setData({ email: devQuickLogin.email, password: devQuickLogin.password, remember: true });
    };

    return (
        <GuestLayout
            title="مرحباً بعودتك"
            subtitle="سجّل دخولك لمتابعة رحلتك في الأكاديمية"
            icon="ri-login-circle-line"
            aside={{
                heading: 'أكمل من حيث توقفت',
                text: 'دوراتك ودروسك بانتظارك — تابع تعلّم التحريك وصناعة المحتوى البصري.',
            }}
        >
            <Head title="تسجيل الدخول" />

            {status && (
                <div className="auth-alert is-success" role="status">
                    <i className="ri-checkbox-circle-line" />
                    <span>{status}</span>
                </div>
            )}

            {devQuickLogin && (
                <div className="auth-dev">
                    <span className="auth-dev-badge">تطوير</span>
                    <div className="auth-dev-text">
                        <strong>{devQuickLogin.label}</strong>
                        <small dir="ltr">
                            {devQuickLogin.email} / {devQuickLogin.password}
                        </small>
                    </div>
                    <button type="button" className="auth-dev-btn" onClick={fillAdmin}>
                        تعبئة
                    </button>
                </div>
            )}

            <form onSubmit={submit} className="auth-form" noValidate>
                <AuthField
                    label="البريد الإلكتروني"
                    icon="ri-mail-line"
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                    error={errors.email}
                    autoComplete="username"
                    placeholder="name@example.com"
                    dir="ltr"
                    autoFocus
                    required
                />

                <AuthField
                    label="كلمة المرور"
                    icon="ri-lock-2-line"
                    type="password"
                    name="password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                    autoComplete="current-password"
                    placeholder="••••••••"
                    required
                    trailing={
                        canResetPassword && (
                            <Link href={route('password.request')} className="auth-link-sm">
                                نسيت كلمة المرور؟
                            </Link>
                        )
                    }
                />

                <label className="auth-check">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <span className="auth-check-box" aria-hidden="true">
                        <i className="ri-check-line" />
                    </span>
                    تذكّرني على هذا الجهاز
                </label>

                <button type="submit" className="auth-submit" disabled={processing}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-arrow-left-line" />
                    )}
                    {processing ? 'جارٍ الدخول...' : 'تسجيل الدخول'}
                </button>
            </form>

            {otpLoginAvailable && (
                <Link href={route('login.otp')} className="auth-whatsapp">
                    <span className="auth-whatsapp-icon">
                        <i className="ri-whatsapp-line" />
                    </span>
                    <span className="auth-whatsapp-text">
                        <strong>الدخول برمز واتساب</strong>
                        <small>بلا كلمة مرور — رمز يصلك على رقمك</small>
                    </span>
                    <i className="ri-arrow-left-s-line" />
                </Link>
            )}

            <div className="auth-divider">
                <span>جديد في الأكاديمية؟</span>
            </div>

            <Link href={route('register')} className="auth-secondary">
                <i className="ri-user-add-line" /> أنشئ حساباً مجانياً
            </Link>
        </GuestLayout>
    );
}
