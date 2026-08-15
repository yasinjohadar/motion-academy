import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ storages, flash }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        backup_type: 'database',
        storage_config_id: storages[0]?.id || '',
        frequency: 'daily',
        time: '02:00',
        day_of_week: 0,
        day_of_month: 1,
        retention_days: 30,
        is_active: true,
    });

    return (
        <AdminUiPage
            title="إنشاء جدولة نسخ"
            subtitle="حدد التكرار ووجهة التخزين"
            flash={flash}
            actions={
                <Link
                    href={route('admin.backup-schedules.index')}
                    className="admin-btn admin-btn-ghost"
                >
                    العودة
                </Link>
            }
        >
            <AdminPanel title="بيانات الجدولة" icon="ri-calendar-schedule-line">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('admin.backup-schedules.store'));
                    }}
                >
                    <ScheduleFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        storages={storages}
                    />
                    <div className="mt-4">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing}
                        >
                            حفظ الجدولة
                        </button>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}

export function ScheduleFields({ data, setData, errors, storages }) {
    return (
        <div className="row g-3">
            <div className="col-md-6">
                <label className="form-label">اسم الجدولة</label>
                <input
                    type="text"
                    className={`form-control${errors.name ? ' is-invalid' : ''}`}
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
                {errors.name && <div className="invalid-feedback">{errors.name}</div>}
            </div>
            <div className="col-md-6">
                <label className="form-label">نوع المحتوى</label>
                <select
                    className="form-select"
                    value={data.backup_type}
                    onChange={(e) => setData('backup_type', e.target.value)}
                >
                    <option value="database">قاعدة البيانات</option>
                    <option value="files">الملفات</option>
                    <option value="full">كاملة</option>
                </select>
            </div>
            <div className="col-md-6">
                <label className="form-label">وجهة التخزين</label>
                <select
                    className={`form-select${errors.storage_config_id ? ' is-invalid' : ''}`}
                    value={data.storage_config_id}
                    onChange={(e) => setData('storage_config_id', Number(e.target.value) || '')}
                >
                    {storages.map((storage) => (
                        <option key={storage.id} value={storage.id}>
                            {storage.name} ({storage.driver_label})
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-md-3">
                <label className="form-label">التكرار</label>
                <select
                    className="form-select"
                    value={data.frequency}
                    onChange={(e) => setData('frequency', e.target.value)}
                >
                    <option value="daily">يومي</option>
                    <option value="weekly">أسبوعي</option>
                    <option value="monthly">شهري</option>
                </select>
            </div>
            <div className="col-md-3">
                <label className="form-label">الوقت</label>
                <input
                    type="time"
                    className="form-control"
                    value={data.time}
                    onChange={(e) => setData('time', e.target.value)}
                />
            </div>
            {data.frequency === 'weekly' && (
                <div className="col-md-4">
                    <label className="form-label">يوم الأسبوع</label>
                    <select
                        className="form-select"
                        value={data.day_of_week}
                        onChange={(e) => setData('day_of_week', Number(e.target.value))}
                    >
                        <option value={0}>الأحد</option>
                        <option value={1}>الإثنين</option>
                        <option value={2}>الثلاثاء</option>
                        <option value={3}>الأربعاء</option>
                        <option value={4}>الخميس</option>
                        <option value={5}>الجمعة</option>
                        <option value={6}>السبت</option>
                    </select>
                </div>
            )}
            {data.frequency === 'monthly' && (
                <div className="col-md-4">
                    <label className="form-label">يوم الشهر (1-28)</label>
                    <input
                        type="number"
                        className="form-control"
                        min={1}
                        max={28}
                        value={data.day_of_month}
                        onChange={(e) => setData('day_of_month', Number(e.target.value) || 1)}
                    />
                </div>
            )}
            <div className="col-md-4">
                <label className="form-label">أيام الاحتفاظ</label>
                <input
                    type="number"
                    className="form-control"
                    value={data.retention_days}
                    onChange={(e) => setData('retention_days', Number(e.target.value) || 30)}
                />
            </div>
            <div className="col-md-4 d-flex align-items-end">
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
    );
}
