import AuthField from '@/Components/Auth/AuthField';
import PasswordStrength from '@/Components/Auth/PasswordStrength';
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

    const mismatch = data.password_confirmation !== '' && data.password !== data.password_confirmation;

    return (
        <GuestLayout
            title="كلمة مرور جديدة"
            subtitle="اختر كلمة مرور قوية لم تستخدمها من قبل"
            icon="ri-lock-password-line"
            aside={{
                heading: 'خطوة أخيرة وتعود',
                text: 'بعد الحفظ يمكنك تسجيل الدخول فوراً بكلمة المرور الجديدة.',
            }}
        >
            <Head title="إعادة تعيين كلمة المرور" />

            <ol className="auth-steps" aria-label="الخطوات">
                <li className="is-done">
                    <span>
                        <i className="ri-check-line" />
                    </span>{' '}
                    البريد
                </li>
                <li className="is-done">
                    <span>
                        <i className="ri-check-line" />
                    </span>{' '}
                    الرابط
                </li>
                <li className="is-current">
                    <span>3</span> كلمة مرور جديدة
                </li>
            </ol>

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
                    dir="ltr"
                    required
                />

                <div>
                    <AuthField
                        label="كلمة المرور الجديدة"
                        icon="ri-lock-2-line"
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(value) => setData('password', value)}
                        error={errors.password}
                        autoComplete="new-password"
                        placeholder="8 أحرف على الأقل"
                        autoFocus
                        required
                    />
                    <PasswordStrength password={data.password} />
                </div>

                <AuthField
                    label="تأكيد كلمة المرور"
                    icon="ri-shield-check-line"
                    type="password"
                    name="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(value) => setData('password_confirmation', value)}
                    error={
                        errors.password_confirmation || (mismatch ? 'كلمتا المرور غير متطابقتين.' : undefined)
                    }
                    autoComplete="new-password"
                    required
                />

                <button type="submit" className="auth-submit" disabled={processing}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-save-3-line" />
                    )}
                    {processing ? 'جارٍ الحفظ...' : 'حفظ كلمة المرور'}
                </button>
            </form>
        </GuestLayout>
    );
}
