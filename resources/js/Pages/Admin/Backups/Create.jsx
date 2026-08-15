import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function Create({ storages, queue: pageQueue, flash }) {
    const sharedQueue = usePage().props.queue;
    const queue = pageQueue || sharedQueue || {};
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        backup_type: 'database',
        storage_config_id: storages[0]?.id || '',
        retention_days: 30,
    });

    return (
        <AdminUiPage
            title="إنشاء نسخة احتياطية"
            subtitle="اختر نوع المحتوى ووجهة التخزين السحابي"
            flash={flash}
            actions={
                <Link href={route('admin.backups.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            {queue.needs_worker && (
                <div className="alert alert-info mb-3" role="alert">
                    <strong>ملاحظة الطابور:</strong> النسخ يعمل عبر Queue (
                    <code>{queue.connection}</code>). قبل البدء شغّل في الطرفية:
                    <pre className="mb-0 mt-2 p-2 bg-light border rounded">
                        {queue.worker_command || 'php artisan queue:work'}
                    </pre>
                    <div className="small mt-2 text-muted">
                        أو ضع <code>BACKUP_DISPATCH_SYNC=true</code> في{' '}
                        <code>.env</code> للتشغيل الفوري بدون عامل.
                    </div>
                </div>
            )}

            <AdminPanel title="إعدادات النسخة" icon="ri-database-2-line">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('admin.backups.store'));
                    }}
                >
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label">اسم النسخة (اختياري)</label>
                            <input
                                type="text"
                                className={`form-control${errors.name ? ' is-invalid' : ''}`}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="backup_..."
                            />
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">نوع المحتوى</label>
                            <select
                                className={`form-select${errors.backup_type ? ' is-invalid' : ''}`}
                                value={data.backup_type}
                                onChange={(e) => setData('backup_type', e.target.value)}
                            >
                                <option value="database">قاعدة البيانات</option>
                                <option value="files">الملفات (storage/app/public)</option>
                                <option value="full">كاملة (قاعدة + ملفات)</option>
                            </select>
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">وجهة التخزين</label>
                            <select
                                className={`form-select${errors.storage_config_id ? ' is-invalid' : ''}`}
                                value={data.storage_config_id}
                                onChange={(e) =>
                                    setData(
                                        'storage_config_id',
                                        Number(e.target.value) || '',
                                    )
                                }
                            >
                                <option value="">اختر إعداد تخزين...</option>
                                {storages.map((storage) => (
                                    <option key={storage.id} value={storage.id}>
                                        {storage.name} ({storage.driver_label})
                                    </option>
                                ))}
                            </select>
                            {errors.storage_config_id && (
                                <div className="invalid-feedback">
                                    {errors.storage_config_id}
                                </div>
                            )}
                        </div>

                        <div className="col-md-6">
                            <label className="form-label">أيام الاحتفاظ</label>
                            <input
                                type="number"
                                className="form-control"
                                value={data.retention_days}
                                onChange={(e) =>
                                    setData(
                                        'retention_days',
                                        Number(e.target.value) || 30,
                                    )
                                }
                                min={1}
                            />
                        </div>
                    </div>

                    <div className="mt-4">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing || !data.storage_config_id}
                        >
                            بدء النسخ
                        </button>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
