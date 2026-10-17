import PhoneField from '@/Components/Auth/PhoneField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function OtpLogin({ countries, defaultCountry }) {
    const { data, setData, post, processing, errors } = useForm({
        country_code: defaultCountry,
        phone: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login.otp.send'));
    };

    return (
        <GuestLayout
            title="الدخول برمز واتساب"
            subtitle="أدخل رقمك المسجّل وسنرسل لك رمز دخول على واتساب — بلا كلمة مرور"
            icon="ri-whatsapp-line"
            aside={{
                heading: 'دخول سريع بلمسة واحدة',
                text: 'رمز من 6 أرقام يصلك على واتساب، صالح لدقائق معدودة ولمرة واحدة فقط.',
            }}
        >
            <Head title="الدخول برمز واتساب" />

            <form onSubmit={submit} className="auth-form" noValidate>
                <PhoneField
                    countries={countries}
                    countryCode={data.country_code}
                    phone={data.phone}
                    onCountryChange={(code) => setData('country_code', code)}
                    onPhoneChange={(value) => setData('phone', value)}
                    error={errors.phone || errors.country_code}
                    hint="الرقم الذي سجّلت به حسابك، ويجب أن يكون عليه واتساب."
                    autoFocus
                />

                <button type="submit" className="auth-submit" disabled={processing || !data.phone}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-send-plane-line" />
                    )}
                    {processing ? 'جارٍ الإرسال...' : 'أرسل رمز الدخول'}
                </button>
            </form>

            <div className="auth-divider">
                <span>أو</span>
            </div>

            <Link href={route('login')} className="auth-secondary">
                <i className="ri-mail-line" /> الدخول بالبريد وكلمة المرور
            </Link>
        </GuestLayout>
    );
}
