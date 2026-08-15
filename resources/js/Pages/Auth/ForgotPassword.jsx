import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout
            title="استعادة كلمة المرور"
            subtitle="أدخل بريدك وسنرسل رابط إعادة التعيين"
        >
            <Head title="استعادة كلمة المرور" />

            {status && (
                <div className="alert alert-success" role="alert">
                    {status}
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

                <div className="d-grid gap-2">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        إرسال رابط الاستعادة
                    </button>
                </div>

                <div className="text-center mt-3 small">
                    <Link href={route('login')} className="link-secondary">
                        العودة لتسجيل الدخول
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
