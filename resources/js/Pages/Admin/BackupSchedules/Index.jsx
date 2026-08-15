import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ schedules, flash }) {
    const { can } = useCan();
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    return (
        <AdminUiPage
            title="جدولة النسخ الاحتياطية"
            subtitle="تشغيل تلقائي يومي / أسبوعي / شهري إلى التخزين السحابي"
            flash={flash}
            actions={
                can('admin.backup-schedules.create') && (
                    <Link
                        href={route('admin.backup-schedules.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-calendar-schedule-line" />
                        جدولة جديدة
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `هل أنت متأكد من حذف الجدولة «${itemToDelete.name}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.backup-schedules.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة الجداول" icon="ri-calendar-schedule-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>النوع</th>
                                    <th>التكرار</th>
                                    <th>التخزين</th>
                                    <th>التشغيل التالي</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {schedules.length === 0 && (
                                    <tr>
                                        <td colSpan="8" className="dashboard-table-empty">
                                            لا توجد جداول بعد
                                        </td>
                                    </tr>
                                )}
                                {schedules.map((schedule, index) => (
                                    <tr key={schedule.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{schedule.name}</strong>
                                        </td>
                                        <td>{schedule.backup_type_label}</td>
                                        <td>
                                            {schedule.frequency_label} · {schedule.time}
                                        </td>
                                        <td>{schedule.storage_config?.name || '—'}</td>
                                        <td className="small">
                                            {schedule.next_run_at || '—'}
                                        </td>
                                        <td>
                                            <span
                                                className={`admin-badge ${schedule.is_active ? 'admin-badge-success' : 'admin-badge-muted'}`}
                                            >
                                                {schedule.is_active ? 'نشط' : 'موقوف'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-2">
                                                {can('admin.backup-schedules.edit') && (
                                                    <>
                                                        <Link
                                                            href={route(
                                                                'admin.backup-schedules.edit',
                                                                schedule.id,
                                                            )}
                                                            className="admin-btn admin-btn-sm admin-btn-ghost"
                                                        >
                                                            تعديل
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="admin-btn admin-btn-sm admin-btn-ghost"
                                                            onClick={() =>
                                                                router.post(
                                                                    route(
                                                                        'admin.backup-schedules.toggle-active',
                                                                        schedule.id,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            {schedule.is_active
                                                                ? 'إيقاف'
                                                                : 'تفعيل'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="admin-btn admin-btn-sm admin-btn-primary"
                                                            onClick={() =>
                                                                router.post(
                                                                    route(
                                                                        'admin.backup-schedules.execute',
                                                                        schedule.id,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            تشغيل الآن
                                                        </button>
                                                    </>
                                                )}
                                                {can('admin.backup-schedules.delete') && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-danger"
                                                        onClick={() =>
                                                            setItemToDelete(schedule)
                                                        }
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </AdminPanel>
        </AdminUiPage>
    );
}
