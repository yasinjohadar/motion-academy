import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            title="تأكيد كلمة المرور"
            subtitle="هذه منطقة محمية. يرجى تأكيد كلمة المرور للمتابعة"
        >
            <Head title="تأكيد كلمة المرور" />

            <form onSubmit={submit}>
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
                        autoFocus
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    {errors.password && (
                        <div className="invalid-feedback">{errors.password}</div>
                    )}
                </div>

                <div className="d-grid">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        تأكيد
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
