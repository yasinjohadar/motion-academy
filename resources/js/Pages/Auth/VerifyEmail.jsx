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
            title="تأكيد البريد الإلكتروني"
            subtitle="شكراً لتسجيلك. يرجى تأكيد بريدك عبر الرابط المرسل إليك"
        >
            <Head title="تأكيد البريد" />

            {status === 'verification-link-sent' && (
                <div className="alert alert-success" role="alert">
                    تم إرسال رابط تأكيد جديد إلى بريدك الإلكتروني.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={processing}
                    >
                        إعادة إرسال رابط التأكيد
                    </button>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="btn btn-outline-secondary"
                    >
                        تسجيل الخروج
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
