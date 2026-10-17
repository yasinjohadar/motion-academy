import AuthField from '@/Components/Auth/AuthField';
import PasswordStrength from '@/Components/Auth/PasswordStrength';
import PhoneField from '@/Components/Auth/PhoneField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register({ countries = [], defaultCountry = '963' }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        country_code: defaultCountry,
        phone: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const mismatch = data.password_confirmation !== '' && data.password !== data.password_confirmation;

    return (
        <GuestLayout
            title="أنشئ حسابك"
            subtitle="انضم إلى الأكاديمية وابدأ بدرس مجاني من أي دورة"
            icon="ri-user-add-line"
            aside={{
                heading: 'ابدأ مشهدك الأول اليوم',
                text: 'حساب واحد يفتح لك الدورات والدروس المجانية للمعاينة ومقالات المدونة.',
            }}
        >
            <Head title="إنشاء حساب" />

            <form onSubmit={submit} className="auth-form" noValidate>
                <AuthField
                    label="الاسم الكامل"
                    icon="ri-user-line"
                    name="name"
                    value={data.name}
                    onChange={(value) => setData('name', value)}
                    error={errors.name}
                    autoComplete="name"
                    placeholder="اسمك كما تحب أن يظهر"
                    autoFocus
                    required
                />

                <AuthField
                    label="البريد الإلكتروني"
                    icon="ri-mail-line"
                    type="email"
                    name="email"
                    value={data.email}
                    onChange={(value) => setData('email', value)}
                    error={errors.email}
                    autoComplete="username"
                    placeholder="name@example.com"
                    dir="ltr"
                    required
                />

                <PhoneField
                    countries={countries}
                    countryCode={data.country_code}
                    phone={data.phone}
                    onCountryChange={(code) => setData('country_code', code)}
                    onPhoneChange={(value) => setData('phone', value)}
                    error={errors.phone || errors.country_code}
                    hint="رقم عليه واتساب — تستخدمه للدخول برمز ولاستعادة كلمة المرور."
                />

                <div className="auth-row">
                    <div>
                        <AuthField
                            label="كلمة المرور"
                            icon="ri-lock-2-line"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            error={errors.password}
                            autoComplete="new-password"
                            placeholder="8 أحرف على الأقل"
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
                            errors.password_confirmation ||
                            (mismatch ? 'كلمتا المرور غير متطابقتين.' : undefined)
                        }
                        autoComplete="new-password"
                        placeholder="أعد كتابتها"
                        required
                    />
                </div>

                <button type="submit" className="auth-submit" disabled={processing}>
                    {processing ? (
                        <span className="auth-spinner" aria-hidden="true" />
                    ) : (
                        <i className="ri-rocket-2-line" />
                    )}
                    {processing ? 'جارٍ إنشاء الحساب...' : 'إنشاء الحساب'}
                </button>
            </form>

            <div className="auth-divider">
                <span>لديك حساب بالفعل؟</span>
            </div>

            <Link href={route('login')} className="auth-secondary">
                <i className="ri-login-circle-line" /> سجّل الدخول
            </Link>
        </GuestLayout>
    );
}
