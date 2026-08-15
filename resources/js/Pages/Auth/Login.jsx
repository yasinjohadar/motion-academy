import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword, devQuickLogin }) {
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

        setData({
            ...data,
            email: devQuickLogin.email,
            password: devQuickLogin.password,
            remember: true,
        });
    };

    return (
        <GuestLayout
            title="تسجيل الدخول"
            subtitle="أدخل بياناتك للوصول إلى لوحة التحكم"
        >
            <Head title="تسجيل الدخول" />

            {status && (
                <div className="alert alert-success" role="alert">
                    {status}
                </div>
            )}

            {devQuickLogin && (
                <div className="guest-dev-login">
                    <div className="guest-dev-login-meta">
                        <span className="guest-dev-login-badge">تطوير</span>
                        <div>
                            <strong>{devQuickLogin.label}</strong>
                            <p>
                                {devQuickLogin.email} / {devQuickLogin.password}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-primary"
                        onClick={fillAdmin}
                    >
                        تعبئة
                    </button>
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mb-3">
                    <label htmlFor="email" className="form-label">
                        البريد الإلكتروني
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className={`form-control${errors.email ? ' is-invalid' : ''}`}
                        autoComplete="username"
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && (
                        <div className="invalid-feedback">{errors.email}</div>
                    )}
                </div>

                <div className="mb-3">
                    <label htmlFor="password" className="form-label">
                        كلمة المرور
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={`form-control${errors.password ? ' is-invalid' : ''}`}
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <div className="invalid-feedback">{errors.password}</div>
                    )}
                </div>

                <div className="mb-3 form-check">
                    <input
                        id="remember"
                        type="checkbox"
                        name="remember"
                        className="form-check-input"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    <label className="form-check-label" htmlFor="remember">
                        تذكرني
                    </label>
                </div>

                <div className="d-grid gap-2">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        دخول
                    </button>
                </div>

                <div className="d-flex justify-content-between align-items-center mt-3 small">
                    {canResetPassword && (
                        <Link href={route('password.request')} className="link-secondary">
                            نسيت كلمة المرور؟
                        </Link>
                    )}
                    <Link href={route('register')} className="link-primary">
                        إنشاء حساب
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
