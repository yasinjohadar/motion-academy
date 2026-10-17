import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { useForm } from '@inertiajs/react';

const PLACEHOLDERS = [
    { key: '{code}', label: 'الرمز (إلزامي)' },
    { key: '{minutes}', label: 'مدة الصلاحية' },
    { key: '{purpose}', label: 'الغرض (تسجيل الدخول…)' },
    { key: '{site}', label: 'اسم الموقع' },
];

function Toggle({ id, checked, onChange, title, desc, icon }) {
    return (
        <label htmlFor={id} className={`otp-toggle${checked ? ' is-on' : ''}`}>
            <span className="otp-toggle-icon">
                <i className={icon} />
            </span>
            <span className="flex-grow-1">
                <strong className="d-block">{title}</strong>
                <small className="text-muted">{desc}</small>
            </span>
            <span className="form-check form-switch m-0">
                <input
                    id={id}
                    type="checkbox"
                    className="form-check-input"
                    checked={checked}
                    onChange={(e) => onChange(e.target.checked)}
                />
            </span>
        </label>
    );
}

export default function Otp({
    settings,
    defaultTemplate,
    readiness,
    countries,
    defaultCountry,
    myPhone,
    flash,
}) {
    const { can } = useCan();
    const canEdit = can('admin.otp-settings.edit');

    const form = useForm({ ...settings });
    const test = useForm({
        country_code: myPhone?.phone ? myPhone.country_code : defaultCountry,
        phone: myPhone?.phone || '',
    });

    const preview = (form.data.message_template || '')
        .replaceAll('{code}', '482916')
        .replaceAll('{minutes}', String(form.data.ttl_minutes))
        .replaceAll('{purpose}', 'تسجيل الدخول')
        .replaceAll('{site}', 'Motion Academy');

    const insert = (token) => form.setData('message_template', `${form.data.message_template}${token}`);

    const save = (e) => {
        e.preventDefault();
        form.put(route('admin.otp-settings.update'), { preserveScroll: true });
    };

    const sendTest = (e) => {
        e.preventDefault();
        test.post(route('admin.otp-settings.test'), { preserveScroll: true });
    };

    const coverage =
        readiness.users_total > 0
            ? Math.round((readiness.users_with_phone / readiness.users_total) * 100)
            : 0;

    return (
        <AdminUiPage
            title="رموز التحقق (OTP)"
            subtitle="الدخول برمز واتساب واستعادة كلمة المرور عبر واتساب"
            eyebrowIcon="ri-shield-keyhole-line"
            flash={flash}
            chips={[
                {
                    label: 'قناة الإرسال',
                    value: readiness.channel ? 'واتساب جاهز' : 'غير مضبوطة',
                    icon: 'ri-whatsapp-line',
                },
                {
                    label: 'حسابات برقم صالح',
                    value: `${readiness.users_with_phone} / ${readiness.users_total}`,
                    icon: 'ri-smartphone-line',
                },
            ]}
        >
            {!readiness.channel && (
                <div className="admin-alert admin-alert-error mb-3">
                    لا توجد قناة إرسال مضبوطة (وحدة واتساب غير مثبّتة)، لذلك الميزتان مخفيتان عن الزوار مهما
                    كانت الإعدادات.
                </div>
            )}

            <form onSubmit={save}>
                <div className="row g-4">
                    <div className="col-xl-7">
                        <AdminPanel title="الميزات" icon="ri-toggle-line">
                            <div className="d-grid gap-2">
                                <Toggle
                                    id="login_enabled"
                                    icon="ri-login-circle-line"
                                    title="الدخول برمز واتساب"
                                    desc="يظهر زر «الدخول برمز واتساب» في صفحة تسجيل الدخول."
                                    checked={form.data.login_enabled}
                                    onChange={(value) => form.setData('login_enabled', value)}
                                />
                                <Toggle
                                    id="reset_enabled"
                                    icon="ri-key-2-line"
                                    title="استعادة كلمة المرور عبر واتساب"
                                    desc="يظهر تبويب «عبر واتساب» في صفحة نسيت كلمة المرور."
                                    checked={form.data.reset_enabled}
                                    onChange={(value) => form.setData('reset_enabled', value)}
                                />
                            </div>
                        </AdminPanel>

                        <AdminPanel title="نص الرسالة" icon="ri-chat-3-line" className="mt-4">
                            <div className="d-flex flex-wrap gap-2 mb-2">
                                {PLACEHOLDERS.map((p) => (
                                    <button
                                        key={p.key}
                                        type="button"
                                        className="otp-chip"
                                        onClick={() => insert(p.key)}
                                        title={p.label}
                                    >
                                        <code dir="ltr">{p.key}</code> {p.label}
                                    </button>
                                ))}
                            </div>
                            <textarea
                                rows={4}
                                className={`form-control${form.errors.message_template ? ' is-invalid' : ''}`}
                                value={form.data.message_template}
                                onChange={(e) => form.setData('message_template', e.target.value)}
                            />
                            {form.errors.message_template && (
                                <div className="invalid-feedback">{form.errors.message_template}</div>
                            )}
                            <button
                                type="button"
                                className="btn btn-link btn-sm px-0"
                                onClick={() => form.setData('message_template', defaultTemplate)}
                            >
                                استعادة النص الافتراضي
                            </button>

                            <div className="otp-wa-preview mt-2">
                                <span className="otp-wa-label">
                                    <i className="ri-whatsapp-line" /> معاينة
                                </span>
                                <div className="otp-wa-bubble">{preview}</div>
                            </div>
                        </AdminPanel>
                    </div>

                    <div className="col-xl-5">
                        <AdminPanel title="الحدود والأمان" icon="ri-shield-check-line">
                            <div className="row g-3">
                                {[
                                    ['ttl_minutes', 'مدة صلاحية الرمز (دقائق)', 1, 30],
                                    ['max_attempts', 'محاولات الإدخال لكل رمز', 1, 10],
                                    [
                                        'resend_cooldown_seconds',
                                        'الانتظار قبل إعادة الإرسال (ثوانٍ)',
                                        15,
                                        600,
                                    ],
                                ].map(([key, label, min, max]) => (
                                    <div className="col-12" key={key}>
                                        <label className="form-label">{label}</label>
                                        <input
                                            type="number"
                                            min={min}
                                            max={max}
                                            className={`form-control${form.errors[key] ? ' is-invalid' : ''}`}
                                            value={form.data[key]}
                                            onChange={(e) => form.setData(key, e.target.value)}
                                        />
                                        {form.errors[key] && (
                                            <div className="invalid-feedback">{form.errors[key]}</div>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <p className="form-text mt-3 mb-0">
                                الرموز تُخزَّن مشفّرة، ولا يكشف النظام هل الرقم مسجّل أم لا، وطلب الرموز محدود
                                لكل رقم.
                            </p>
                        </AdminPanel>

                        <AdminPanel title="جاهزية الأرقام" icon="ri-smartphone-line" className="mt-4">
                            <div className="d-flex justify-content-between mb-1">
                                <span>حسابات لها رقم صالح</span>
                                <strong>{coverage}%</strong>
                            </div>
                            <div className="progress" style={{ height: 8 }}>
                                <div className="progress-bar bg-success" style={{ width: `${coverage}%` }} />
                            </div>
                            <p className="form-text mt-2 mb-0">
                                الحسابات بلا رقم صالح لا تستطيع الدخول برمز واتساب. يضيف المستخدم رقمه من
                                الملف الشخصي.
                            </p>
                        </AdminPanel>
                    </div>

                    {canEdit && (
                        <div className="col-12">
                            <button
                                type="submit"
                                className="admin-btn admin-btn-primary"
                                disabled={form.processing}
                            >
                                <i className="ri-save-line" /> حفظ الإعدادات
                            </button>
                        </div>
                    )}
                </div>
            </form>

            {canEdit && (
                <AdminPanel title="إرسال رمز تجريبي" icon="ri-send-plane-line" className="mt-4">
                    <form onSubmit={sendTest} className="row g-3 align-items-start">
                        <div className="col-md-3">
                            <label className="form-label">مفتاح الدولة</label>
                            <select
                                className="form-select"
                                value={test.data.country_code}
                                onChange={(e) => test.setData('country_code', e.target.value)}
                            >
                                {countries.map((country) => (
                                    <option key={country.code + country.iso} value={country.code}>
                                        {country.flag} {country.name} (+{country.code})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-5">
                            <label className="form-label">رقم واتساب</label>
                            <input
                                type="tel"
                                dir="ltr"
                                className={`form-control${test.errors.phone ? ' is-invalid' : ''}`}
                                value={test.data.phone}
                                placeholder="991234567"
                                onChange={(e) => test.setData('phone', e.target.value.replace(/\D+/g, ''))}
                            />
                            {test.errors.phone && <div className="invalid-feedback">{test.errors.phone}</div>}
                        </div>
                        <div className="col-md-4">
                            <label className="form-label d-none d-md-block">&nbsp;</label>
                            <button
                                type="submit"
                                className="admin-btn admin-btn-primary w-100"
                                disabled={test.processing || !readiness.channel}
                            >
                                <i className="ri-whatsapp-line" /> إرسال رمز تجريبي
                            </button>
                        </div>
                        <div className="col-12 form-text mt-0">
                            يُرسل رمزاً عشوائياً عبر نفس القناة بالنص المحفوظ (احفظ التعديلات أولاً). لا
                            يُخزَّن الرمز ولا يصلح للدخول.
                        </div>
                    </form>
                </AdminPanel>
            )}
        </AdminUiPage>
    );
}
