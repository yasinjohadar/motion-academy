import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import StorageDriverFields, { emptyDriverConfig } from '@/Components/Admin/StorageDriverFields';
import useCan from '@/hooks/useCan';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Edit({ config, drivers, flash }) {
    const { can } = useCan();
    const [testing, setTesting] = useState(false);
    const baseConfig = {
        ...emptyDriverConfig(config.driver),
        ...(config.config || {}),
        access_key_id: '',
        secret_access_key: '',
    };

    const { data, setData, put, processing, errors } = useForm({
        name: config.name || '',
        driver: config.driver || 's3',
        config: baseConfig,
        is_active: !!config.is_active,
        priority: config.priority ?? 0,
        cdn_url: config.cdn_url || '',
    });

    const changeDriver = (driver) => {
        setData({
            ...data,
            driver,
            config: emptyDriverConfig(driver),
        });
    };

    return (
        <AdminUiPage
            title="تعديل إعداد التخزين"
            subtitle={config.name}
            flash={flash}
            actions={
                <Link href={route('admin.app-storage.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <AdminPanel title="بيانات الإعداد" icon="ri-cloud-line">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('admin.app-storage.update', config.id));
                    }}
                >
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label">اسم الإعداد</label>
                            <input
                                type="text"
                                className={`form-control${errors.name ? ' is-invalid' : ''}`}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            {errors.name && (
                                <div className="invalid-feedback">{errors.name}</div>
                            )}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">نوع التخزين</label>
                            <select
                                className={`form-select${errors.driver ? ' is-invalid' : ''}`}
                                value={data.driver}
                                onChange={(e) => changeDriver(e.target.value)}
                            >
                                {Object.entries(drivers).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <StorageDriverFields
                            driver={data.driver}
                            config={data.config}
                            errors={errors}
                            onChange={(next) => setData('config', next)}
                            editing
                            hasSecretAccessKey={config.has_secret_access_key}
                        />

                        <div className="col-md-6">
                            <label className="form-label">رابط CDN (اختياري)</label>
                            <input
                                type="url"
                                className="form-control"
                                value={data.cdn_url}
                                onChange={(e) => setData('cdn_url', e.target.value)}
                            />
                        </div>

                        <div className="col-md-3">
                            <label className="form-label">الأولوية</label>
                            <input
                                type="number"
                                className="form-control"
                                value={data.priority}
                                onChange={(e) =>
                                    setData('priority', Number(e.target.value) || 0)
                                }
                            />
                        </div>

                        <div className="col-md-3 d-flex align-items-end">
                            <div className="form-check mb-2">
                                <input
                                    id="is_active"
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                />
                                <label className="form-check-label" htmlFor="is_active">
                                    نشط
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="d-flex flex-wrap gap-2 mt-4">
                        {can('admin.app-storage.test') && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                disabled={testing}
                                onClick={() => {
                                    setTesting(true);
                                    router.post(
                                        route('admin.app-storage.test-draft'),
                                        {
                                            driver: data.driver,
                                            config: data.config,
                                            config_id: config.id,
                                            cdn_url: data.cdn_url,
                                        },
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            onFinish: () => setTesting(false),
                                        },
                                    );
                                }}
                            >
                                {testing ? 'جاري الاختبار...' : 'اختبار الاتصال'}
                            </button>
                        )}
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing}
                        >
                            حفظ التعديلات
                        </button>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
