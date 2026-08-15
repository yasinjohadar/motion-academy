import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Create({ flash }) {
    const { can } = useCan();
    const { errors: pageErrors } = usePage().props;
    const [testing, setTesting] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        host: '',
        port: 587,
        username: '',
        password: '',
        encryption: 'tls',
        from_address: '',
        from_name: '',
        is_default: false,
        is_active: true,
        test_to: '',
    });

    const fieldErrors = { ...pageErrors, ...errors };

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.smtp-settings.store'));
    };

    const runTest = () => {
        if (!can('admin.smtp-settings.test')) {
            return;
        }

        setTesting(true);
        router.post(
            route('admin.smtp-settings.test-draft'),
            {
                to: data.test_to || data.from_address,
                name: data.name,
                host: data.host,
                port: data.port,
                username: data.username,
                password: data.password,
                encryption: data.encryption,
                from_address: data.from_address,
                from_name: data.from_name,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setTesting(false),
            },
        );
    };

    return (
        <AdminUiPage
            title="إنشاء إعداد SMTP"
            subtitle="اختبر الاتصال قبل الحفظ، ثم أضف الإعداد للنظام"
            flash={flash}
            actions={
                <Link href={route('admin.smtp-settings.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات الإعداد" icon="ri-mail-add-line">
                <form onSubmit={submit} className="row g-3">
                    <div className="col-md-6">
                        <label className="form-label">اسم الإعداد</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.name ? ' is-invalid' : ''}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Gmail / Outlook / SendGrid"
                        />
                        {fieldErrors.name && (
                            <div className="invalid-feedback">{fieldErrors.name}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">التشفير</label>
                        <select
                            className={`form-select${fieldErrors.encryption ? ' is-invalid' : ''}`}
                            value={data.encryption}
                            onChange={(e) => setData('encryption', e.target.value)}
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">بدون</option>
                        </select>
                        {fieldErrors.encryption && (
                            <div className="invalid-feedback">{fieldErrors.encryption}</div>
                        )}
                    </div>

                    <div className="col-md-8">
                        <label className="form-label">المضيف (Host)</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.host ? ' is-invalid' : ''}`}
                            value={data.host}
                            onChange={(e) => setData('host', e.target.value)}
                            placeholder="smtp.gmail.com"
                            dir="ltr"
                        />
                        {fieldErrors.host && (
                            <div className="invalid-feedback">{fieldErrors.host}</div>
                        )}
                    </div>

                    <div className="col-md-4">
                        <label className="form-label">المنفذ</label>
                        <input
                            type="number"
                            className={`form-control${fieldErrors.port ? ' is-invalid' : ''}`}
                            value={data.port}
                            onChange={(e) => setData('port', Number(e.target.value))}
                            dir="ltr"
                        />
                        {fieldErrors.port && (
                            <div className="invalid-feedback">{fieldErrors.port}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">اسم المستخدم</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.username ? ' is-invalid' : ''}`}
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            dir="ltr"
                        />
                        {fieldErrors.username && (
                            <div className="invalid-feedback">{fieldErrors.username}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">كلمة المرور</label>
                        <input
                            type="password"
                            className={`form-control${fieldErrors.password ? ' is-invalid' : ''}`}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            dir="ltr"
                        />
                        {fieldErrors.password && (
                            <div className="invalid-feedback">{fieldErrors.password}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">بريد المرسل</label>
                        <input
                            type="email"
                            className={`form-control${fieldErrors.from_address ? ' is-invalid' : ''}`}
                            value={data.from_address}
                            onChange={(e) => setData('from_address', e.target.value)}
                            dir="ltr"
                        />
                        {fieldErrors.from_address && (
                            <div className="invalid-feedback">{fieldErrors.from_address}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">اسم المرسل</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.from_name ? ' is-invalid' : ''}`}
                            value={data.from_name}
                            onChange={(e) => setData('from_name', e.target.value)}
                        />
                        {fieldErrors.from_name && (
                            <div className="invalid-feedback">{fieldErrors.from_name}</div>
                        )}
                    </div>

                    <div className="col-12 d-flex flex-wrap gap-4">
                        <label className="d-inline-flex align-items-center gap-2 mb-0">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                            />
                            نشط
                        </label>
                        <label className="d-inline-flex align-items-center gap-2 mb-0">
                            <input
                                type="checkbox"
                                checked={data.is_default}
                                onChange={(e) => setData('is_default', e.target.checked)}
                            />
                            تعيين كافتراضي
                        </label>
                    </div>

                    {can('admin.smtp-settings.test') && (
                        <div className="col-12">
                            <div className="border rounded-3 p-3 bg-light">
                                <label className="form-label">بريد المستلم للاختبار</label>
                                <div className="row g-2 align-items-end">
                                    <div className="col-md-8">
                                        <input
                                            type="email"
                                            className={`form-control${fieldErrors.to ? ' is-invalid' : ''}`}
                                            value={data.test_to}
                                            onChange={(e) => setData('test_to', e.target.value)}
                                            placeholder={data.from_address || 'you@example.com'}
                                            dir="ltr"
                                        />
                                        {fieldErrors.to && (
                                            <div className="invalid-feedback d-block">
                                                {fieldErrors.to}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <button
                                            type="button"
                                            className="admin-btn admin-btn-primary w-100"
                                            disabled={testing || processing}
                                            onClick={runTest}
                                        >
                                            <i className="ri-send-plane-line" />
                                            {testing ? 'جاري الاختبار...' : 'اختبار قبل الحفظ'}
                                        </button>
                                    </div>
                                </div>
                                <div className="form-text mt-2">
                                    يرسل رسالة تجريبية بالإعدادات الحالية دون حفظها
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="col-12 d-flex gap-2">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing || testing}
                        >
                            <i className="ri-save-line" />
                            حفظ الإعداد
                        </button>
                        <Link href={route('admin.smtp-settings.index')} className="admin-btn admin-btn-ghost">
                            إلغاء
                        </Link>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
