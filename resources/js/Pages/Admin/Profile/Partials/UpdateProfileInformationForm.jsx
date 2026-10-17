import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformationForm({
    mustVerifyEmail,
    status,
    phone = { country_code: '963', phone: '', verified: false },
    countries = [],
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
        country_code: phone.country_code,
        phone: phone.phone,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('admin.profile.update'));
    };

    return (
        <form onSubmit={submit}>
            <div className="mb-3">
                <label htmlFor="name" className="form-label">
                    الاسم
                </label>
                <input
                    id="name"
                    className={`form-control${errors.name ? ' is-invalid' : ''}`}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    autoComplete="name"
                />
                {errors.name && <div className="invalid-feedback">{errors.name}</div>}
            </div>

            <div className="mb-3">
                <label htmlFor="email" className="form-label">
                    البريد الإلكتروني
                </label>
                <input
                    id="email"
                    type="email"
                    className={`form-control${errors.email ? ' is-invalid' : ''}`}
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                    autoComplete="username"
                />
                {errors.email && <div className="invalid-feedback">{errors.email}</div>}
            </div>

            <div className="mb-3">
                <label htmlFor="phone" className="form-label d-flex align-items-center gap-2">
                    رقم الهاتف (واتساب)
                    {phone.phone &&
                        (phone.verified ? (
                            <span className="admin-badge admin-badge-success">مؤكد</span>
                        ) : (
                            <span className="admin-badge admin-badge-muted">غير مؤكد</span>
                        ))}
                </label>
                <div className="input-group" dir="ltr">
                    <select
                        className="form-select"
                        style={{ maxWidth: 150 }}
                        value={data.country_code}
                        onChange={(e) => setData('country_code', e.target.value)}
                        aria-label="مفتاح الدولة"
                    >
                        {countries.map((country) => (
                            <option key={country.code + country.iso} value={country.code}>
                                {country.flag} +{country.code}
                            </option>
                        ))}
                    </select>
                    <input
                        id="phone"
                        type="tel"
                        inputMode="numeric"
                        className={`form-control${errors.phone ? ' is-invalid' : ''}`}
                        value={data.phone}
                        placeholder="991234567"
                        onChange={(e) =>
                            setData('phone', e.target.value.replace(/\D+/g, '').replace(/^0+/, ''))
                        }
                        autoComplete="tel-national"
                    />
                    {errors.phone && (
                        <div className="invalid-feedback" dir="rtl">
                            {errors.phone}
                        </div>
                    )}
                </div>
                <div className="form-text">
                    يُستخدم للدخول برمز واتساب ولاستعادة كلمة المرور. يتأكد تلقائياً عند أول دخول برمز.
                </div>
            </div>

            {mustVerifyEmail && user.email_verified_at === null && (
                <div className="mb-3">
                    <p className="small text-secondary mb-2">
                        بريدك غير مؤكد.{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="btn btn-link btn-sm p-0 align-baseline"
                        >
                            إعادة إرسال رابط التأكيد
                        </Link>
                    </p>
                    {status === 'verification-link-sent' && (
                        <div className="alert alert-success py-2" role="alert">
                            تم إرسال رابط تأكيد جديد إلى بريدك.
                        </div>
                    )}
                </div>
            )}

            <div className="d-flex align-items-center gap-3">
                <button type="submit" className="btn btn-primary" disabled={processing}>
                    حفظ
                </button>
                {recentlySuccessful && <span className="small text-success">تم الحفظ.</span>}
            </div>
        </form>
    );
}
