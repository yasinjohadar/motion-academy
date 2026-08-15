import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Edit({ provider, drivers = {}, flash }) {
    const { can } = useCan();
    const { errors: pageErrors } = usePage().props;
    const [testing, setTesting] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        name: provider.name || '',
        driver: provider.driver || 'openai',
        api_key: '',
        base_url: provider.base_url || '',
        default_model: provider.default_model || '',
        is_default: Boolean(provider.is_default),
        is_active: Boolean(provider.is_active),
    });

    const fieldErrors = { ...pageErrors, ...errors };
    const needsBaseUrl = useMemo(
        () => ['ollama', 'openai-compatible'].includes(data.driver),
        [data.driver],
    );
    const needsApiKey = data.driver !== 'ollama';

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.ai-providers.update', provider.id));
    };

    const runTest = () => {
        if (!can('admin.ai-providers.test')) {
            return;
        }

        setTesting(true);
        router.post(
            route('admin.ai-providers.test', provider.id),
            {
                api_key: data.api_key || undefined,
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
            title="تعديل مزود الذكاء الاصطناعي"
            subtitle="اترك مفتاح API فارغاً للإبقاء على المفتاح الحالي"
            flash={flash}
            actions={
                <Link href={route('admin.ai-providers.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات المزود" icon="ri-robot-2-line">
                <form onSubmit={submit} className="row g-3">
                    <div className="col-md-6">
                        <label className="form-label">اسم الإعداد</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.name ? ' is-invalid' : ''}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {fieldErrors.name && (
                            <div className="invalid-feedback">{fieldErrors.name}</div>
                        )}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">نوع المزود</label>
                        <select
                            className={`form-select${fieldErrors.driver ? ' is-invalid' : ''}`}
                            value={data.driver}
                            onChange={(e) => setData('driver', e.target.value)}
                        >
                            {Object.entries(drivers).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        {fieldErrors.driver && (
                            <div className="invalid-feedback">{fieldErrors.driver}</div>
                        )}
                    </div>

                    {needsApiKey && (
                        <div className="col-md-12">
                            <label className="form-label">مفتاح API</label>
                            <input
                                type="password"
                                className={`form-control${fieldErrors.api_key ? ' is-invalid' : ''}`}
                                value={data.api_key}
                                onChange={(e) => setData('api_key', e.target.value)}
                                dir="ltr"
                                autoComplete="new-password"
                                placeholder={
                                    provider.has_api_key
                                        ? '•••••••• (اتركه فارغاً للإبقاء)'
                                        : 'أدخل مفتاح API'
                                }
                            />
                            {fieldErrors.api_key && (
                                <div className="invalid-feedback">{fieldErrors.api_key}</div>
                            )}
                        </div>
                    )}

                    <div className="col-md-8">
                        <label className="form-label">
                            Base URL {needsBaseUrl ? '' : '(اختياري)'}
                        </label>
                        <input
                            type="url"
                            className={`form-control${fieldErrors.base_url ? ' is-invalid' : ''}`}
                            value={data.base_url}
                            onChange={(e) => setData('base_url', e.target.value)}
                            dir="ltr"
                        />
                        {fieldErrors.base_url && (
                            <div className="invalid-feedback">{fieldErrors.base_url}</div>
                        )}
                    </div>

                    <div className="col-md-4">
                        <label className="form-label">النموذج الافتراضي</label>
                        <input
                            type="text"
                            className={`form-control${fieldErrors.default_model ? ' is-invalid' : ''}`}
                            value={data.default_model}
                            onChange={(e) => setData('default_model', e.target.value)}
                            dir="ltr"
                        />
                        {fieldErrors.default_model && (
                            <div className="invalid-feedback">{fieldErrors.default_model}</div>
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

                    {can('admin.ai-providers.test') && (
                        <div className="col-12">
                            <div className="border rounded-3 p-3 bg-light">
                                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <strong>اختبار الاتصال</strong>
                                        <div className="form-text mb-0">
                                            يستخدم المفتاح المحفوظ أو المفتاح الجديد إن أدخلته
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        className="admin-btn admin-btn-primary"
                                        disabled={testing || processing}
                                        onClick={runTest}
                                    >
                                        <i className="ri-wifi-line" />
                                        {testing ? 'جاري الاختبار...' : 'اختبار الاتصال'}
                                    </button>
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
                            حفظ التعديلات
                        </button>
                        <Link href={route('admin.ai-providers.index')} className="admin-btn admin-btn-ghost">
                            إلغاء
                        </Link>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
