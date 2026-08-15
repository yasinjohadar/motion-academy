import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm } from '@inertiajs/react';
import { ScheduleFields } from './Create';

export default function Edit({ schedule, storages, flash }) {
    const { data, setData, put, processing, errors } = useForm({
        name: schedule.name || '',
        backup_type: schedule.backup_type || 'database',
        storage_config_id: schedule.storage_config_id || '',
        frequency: schedule.frequency || 'daily',
        time: schedule.time || '02:00',
        day_of_week: schedule.day_of_week ?? 0,
        day_of_month: schedule.day_of_month ?? 1,
        retention_days: schedule.retention_days ?? 30,
        is_active: !!schedule.is_active,
    });

    return (
        <AdminUiPage
            title="تعديل الجدولة"
            subtitle={schedule.name}
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
                        put(route('admin.backup-schedules.update', schedule.id));
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
                            حفظ التعديلات
                        </button>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
