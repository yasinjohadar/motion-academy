import AuthField from '@/Components/Auth/AuthField';
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
            title="تأكيد هويتك"
            subtitle="هذه منطقة محمية. أدخل كلمة المرور للمتابعة"
            icon="ri-shield-keyhole-line"
        >
            <Head title="تأكيد كلمة المرور" />

            <form onSubmit={submit} className="auth-form" noValidate>
                <AuthField
                    label="كلمة المرور"
                    icon="ri-lock-2-line"
                    type="password"
                    name="password"
                    value={data.password}
                    onChange={(value) => setData('password', value)}
                    error={errors.password}
                    autoComplete="current-password"
                    autoFocus
                    required
                />

                <button type="submit" className="auth-submit" disabled={processing}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-shield-check-line" />
                    )}
                    {processing ? 'جارٍ التأكيد...' : 'تأكيد'}
                </button>
            </form>
        </GuestLayout>
    );
}
