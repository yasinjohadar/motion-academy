import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            title="إعادة تعيين كلمة المرور"
            subtitle="أدخل كلمة مرور جديدة لحسابك"
        >
            <Head title="إعادة تعيين كلمة المرور" />

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
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    {errors.email && (
                        <div className="invalid-feedback">{errors.email}</div>
                    )}
                </div>

                <div className="mb-3">
                    <label htmlFor="password" className="form-label">
                        كلمة المرور الجديدة
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className={`form-control${errors.password ? ' is-invalid' : ''}`}
                        autoComplete="new-password"
                        autoFocus
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

                <div className="d-grid">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        حفظ كلمة المرور
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
