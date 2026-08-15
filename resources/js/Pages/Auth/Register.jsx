import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            title="إنشاء حساب"
            subtitle="سجّل حساباً جديداً للوصول إلى لوحة التحكم"
        >
            <Head title="إنشاء حساب" />

            <form onSubmit={submit}>
                <div className="mb-3">
                    <label htmlFor="name" className="form-label">
                        الاسم
                    </label>
                    <input
                        id="name"
                        name="name"
                        value={data.name}
                        className={`form-control${errors.name ? ' is-invalid' : ''}`}
                        autoComplete="name"
                        autoFocus
                        required
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    {errors.name && (
                        <div className="invalid-feedback">{errors.name}</div>
                    )}
                </div>

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
                        required
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
                        autoComplete="new-password"
                        required
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <div className="invalid-feedback">{errors.password}</div>
                    )}
                </div>

                <div className="mb-3">
                    <label htmlFor="password_confirmation" className="form-label">
                        تأكيد كلمة المرور
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className={`form-control${errors.password_confirmation ? ' is-invalid' : ''}`}
                        autoComplete="new-password"
                        required
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                    />
                    {errors.password_confirmation && (
                        <div className="invalid-feedback">
                            {errors.password_confirmation}
                        </div>
                    )}
                </div>

                <div className="d-grid gap-2">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        تسجيل
                    </button>
                </div>

                <div className="text-center mt-3 small">
                    <Link href={route('login')} className="link-secondary">
                        لديك حساب؟ سجّل الدخول
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
