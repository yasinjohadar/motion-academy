import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout
            title="أكّد بريدك الإلكتروني"
            subtitle="أرسلنا إليك رابط تأكيد — اضغط عليه لتفعيل حسابك"
            icon="ri-mail-check-line"
        >
            <Head title="تأكيد البريد" />

            <div className="auth-sent">
                <span className="auth-sent-icon" aria-hidden="true">
                    <i className="ri-mail-open-line" />
                </span>
                <strong>
                    {status === 'verification-link-sent' ? 'أرسلنا رابطاً جديداً' : 'بانتظار التأكيد'}
                </strong>
                <p>
                    {status === 'verification-link-sent'
                        ? 'تم إرسال رابط تأكيد جديد إلى بريدك الإلكتروني.'
                        : 'افتح بريدك واضغط على رابط التأكيد. إن لم تجده فتحقق من الرسائل غير المرغوب فيها.'}
                </p>
            </div>

            <form onSubmit={submit} className="auth-form">
                <button type="submit" className="auth-submit" disabled={processing}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-refresh-line" />
                    )}
                    {processing ? 'جارٍ الإرسال...' : 'إعادة إرسال رابط التأكيد'}
                </button>
            </form>

            <Link
                href={route('logout')}
                method="post"
                as="button"
                className="auth-back-link auth-back-button"
            >
                <i className="ri-logout-box-r-line" /> تسجيل الخروج
            </Link>
        </GuestLayout>
    );
}
