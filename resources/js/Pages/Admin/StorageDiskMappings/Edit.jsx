import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ mapping, logicalDisks, storages, flash }) {
    const { data, setData, put, processing, errors } = useForm({
        disk_name: mapping.disk_name,
        label: mapping.label || '',
        primary_storage_id: mapping.primary_storage_id || '',
        fallback_storage_ids: mapping.fallback_storage_ids || [],
        is_active: !!mapping.is_active,
    });

    const toggleFallback = (id) => {
        const current = data.fallback_storage_ids || [];
        setData(
            'fallback_storage_ids',
            current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
        );
    };

    return (
        <AdminUiPage
            title="تعديل ربط القرص"
            subtitle={mapping.label}
            flash={flash}
            actions={
                <Link
                    href={route('admin.storage-disk-mappings.index')}
                    className="admin-btn admin-btn-ghost"
                >
                    العودة
                </Link>
            }
        >
            <AdminPanel title="بيانات الربط" icon="ri-link">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('admin.storage-disk-mappings.update', mapping.id));
                    }}
                >
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label">القرص المنطقي</label>
                            <select
                                className={`form-select${errors.disk_name ? ' is-invalid' : ''}`}
                                value={data.disk_name}
                                onChange={(e) => setData('disk_name', e.target.value)}
                            >
                                {Object.entries(logicalDisks).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {value} — {label}
                                    </option>
                                ))}
                            </select>
                            {errors.disk_name && (
                                <div className="invalid-feedback">{errors.disk_name}</div>
                            )}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">التسمية</label>
                            <input
                                type="text"
                                className={`form-control${errors.label ? ' is-invalid' : ''}`}
                                value={data.label}
                                onChange={(e) => setData('label', e.target.value)}
                            />
                            {errors.label && (
                                <div className="invalid-feedback">{errors.label}</div>
                            )}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">التخزين الأساسي</label>
                            <select
                                className={`form-select${errors.primary_storage_id ? ' is-invalid' : ''}`}
                                value={data.primary_storage_id}
                                onChange={(e) =>
                                    setData(
                                        'primary_storage_id',
                                        Number(e.target.value) || '',
                                    )
                                }
                            >
                                <option value="">اختر...</option>
                                {storages.map((storage) => (
                                    <option key={storage.id} value={storage.id}>
                                        {storage.name} ({storage.driver_label})
                                    </option>
                                ))}
                            </select>
                            {errors.primary_storage_id && (
                                <div className="invalid-feedback">
                                    {errors.primary_storage_id}
                                </div>
                            )}
                        </div>

                        <div className="col-md-12">
                            <label className="form-label">تخزين احتياطي (اختياري)</label>
                            <div className="d-flex flex-wrap gap-3">
                                {storages
                                    .filter((s) => s.id !== data.primary_storage_id)
                                    .map((storage) => (
                                        <div className="form-check" key={storage.id}>
                                            <input
                                                id={`fallback-${storage.id}`}
                                                type="checkbox"
                                                className="form-check-input"
                                                checked={(
                                                    data.fallback_storage_ids || []
                                                ).includes(storage.id)}
                                                onChange={() => toggleFallback(storage.id)}
                                            />
                                            <label
                                                className="form-check-label"
                                                htmlFor={`fallback-${storage.id}`}
                                            >
                                                {storage.name}
                                            </label>
                                        </div>
                                    ))}
                            </div>
                        </div>

                        <div className="col-md-6 d-flex align-items-end">
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

                    <div className="mt-4">
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
