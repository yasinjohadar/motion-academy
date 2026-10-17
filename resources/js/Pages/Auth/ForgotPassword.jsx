import AuthField from '@/Components/Auth/AuthField';
import PhoneField from '@/Components/Auth/PhoneField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ForgotPassword({
    status,
    whatsappAvailable = false,
    countries = [],
    defaultCountry = '963',
}) {
    const [tab, setTab] = useState(whatsappAvailable ? 'whatsapp' : 'email');

    const emailForm = useForm({ email: '' });
    const phoneForm = useForm({ country_code: defaultCountry, phone: '' });

    const submitEmail = (e) => {
        e.preventDefault();
        emailForm.post(route('password.email'));
    };

    const submitPhone = (e) => {
        e.preventDefault();
        phoneForm.post(route('password.whatsapp.send'));
    };

    return (
        <GuestLayout
            title="نسيت كلمة المرور؟"
            subtitle="لا تقلق — اختر كيف تريد استلام رمز أو رابط الاستعادة"
            icon="ri-key-2-line"
            aside={{
                heading: 'نعيدك إلى مشهدك خلال دقيقة',
                text: 'رمز على واتساب أو رابط على بريدك، ثم تختار كلمة مرور جديدة بنفسك. لن نرسل لك كلمة مرور أبداً.',
            }}
        >
            <Head title="استعادة كلمة المرور" />

            {whatsappAvailable && (
                <div className="auth-tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'whatsapp'}
                        className={`auth-tab${tab === 'whatsapp' ? ' is-active' : ''}`}
                        onClick={() => setTab('whatsapp')}
                    >
                        <i className="ri-whatsapp-line" /> عبر واتساب
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={tab === 'email'}
                        className={`auth-tab${tab === 'email' ? ' is-active' : ''}`}
                        onClick={() => setTab('email')}
                    >
                        <i className="ri-mail-line" /> عبر البريد
                    </button>
                </div>
            )}

            {tab === 'whatsapp' ? (
                <>
                    <ol className="auth-steps" aria-label="الخطوات">
                        <li className="is-current">
                            <span>1</span> الرقم
                        </li>
                        <li>
                            <span>2</span> الرمز
                        </li>
                        <li>
                            <span>3</span> كلمة مرور جديدة
                        </li>
                    </ol>

                    <form onSubmit={submitPhone} className="auth-form" noValidate>
                        <PhoneField
                            countries={countries}
                            countryCode={phoneForm.data.country_code}
                            phone={phoneForm.data.phone}
                            onCountryChange={(code) => phoneForm.setData('country_code', code)}
                            onPhoneChange={(value) => phoneForm.setData('phone', value)}
                            error={phoneForm.errors.phone || phoneForm.errors.country_code}
                            hint="الرقم المسجّل في حسابك، وسيصلك عليه رمز من 6 أرقام."
                            autoFocus
                        />

                        <button
                            type="submit"
                            className="auth-submit"
                            disabled={phoneForm.processing || !phoneForm.data.phone}
                        >
                            {phoneForm.processing ? (
                                <span className="auth-spinner" aria-hidden="true" />
                            ) : (
                                <i className="ri-whatsapp-line" />
                            )}
                            {phoneForm.processing ? 'جارٍ الإرسال...' : 'أرسل الرمز على واتساب'}
                        </button>
                    </form>
                </>
            ) : (
                <>
                    {status ? (
                        <div className="auth-sent">
                            <span className="auth-sent-icon" aria-hidden="true">
                                <i className="ri-mail-send-line" />
                            </span>
                            <strong>تحقق من بريدك الإلكتروني</strong>
                            <p>{status}</p>
                            <small>لم يصلك؟ تحقق من مجلد الرسائل غير المرغوب فيها، أو أعد الإرسال.</small>
                        </div>
                    ) : null}

                    <form onSubmit={submitEmail} className="auth-form" noValidate>
                        <AuthField
                            label="البريد الإلكتروني"
                            icon="ri-mail-line"
                            type="email"
                            name="email"
                            value={emailForm.data.email}
                            onChange={(value) => emailForm.setData('email', value)}
                            error={emailForm.errors.email}
                            autoComplete="username"
                            placeholder="name@example.com"
                            dir="ltr"
                            autoFocus={!whatsappAvailable}
                            required
                        />

                        <button type="submit" className="auth-submit" disabled={emailForm.processing}>
                            {emailForm.processing ? (
                                <span className="auth-spinner" aria-hidden="true" />
                            ) : (
                                <i className="ri-send-plane-line" />
                            )}
                            {emailForm.processing
                                ? 'جارٍ الإرسال...'
                                : status
                                  ? 'إعادة إرسال الرابط'
                                  : 'إرسال رابط الاستعادة'}
                        </button>
                    </form>
                </>
            )}

            <Link href={route('login')} className="auth-back-link">
                <i className="ri-arrow-right-line" /> العودة لتسجيل الدخول
            </Link>
        </GuestLayout>
    );
}
