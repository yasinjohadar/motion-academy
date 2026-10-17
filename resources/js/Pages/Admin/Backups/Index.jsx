import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

const statusBadge = (status) => {
    const map = {
        completed: 'admin-badge-success',
        running: 'admin-badge-warning',
        pending: 'admin-badge-muted',
        failed: 'admin-badge-danger',
    };
    const labels = {
        completed: 'مكتملة',
        running: 'جارية',
        pending: 'معلقة',
        failed: 'فاشلة',
    };
    return (
        <span className={`admin-badge ${map[status] || 'admin-badge-muted'}`}>
            {labels[status] || status}
        </span>
    );
};

export default function Index({ backups, flash }) {
    const { can } = useCan();
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const rows = backups?.data || backups || [];

    return (
        <AdminUiPage
            title="النسخ الاحتياطية"
            subtitle="إنشاء واسترجاع وتنزيل النسخ مع التخزين السحابي"
            flash={flash}
            actions={
                can('admin.backups.create') && (
                    <Link href={route('admin.backups.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-add-circle-line" />
                        نسخة جديدة
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={itemToDelete ? `هل أنت متأكد من حذف النسخة «${itemToDelete.name}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.backups.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة النسخ الاحتياطية" icon="ri-database-2-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>النوع</th>
                                    <th>التخزين</th>
                                    <th>الحجم</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <TableEmptyRow colSpan={8}>لا توجد نسخ احتياطية بعد</TableEmptyRow>
                                )}
                                {rows.map((backup, index) => (
                                    <tr key={backup.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{backup.name}</strong>
                                        </td>
                                        <td>{backup.backup_type_label}</td>
                                        <td>
                                            {backup.storage_config?.name || '—'}
                                            <div className="text-muted small">
                                                {backup.storage_config?.driver_label}
                                            </div>
                                        </td>
                                        <td>{backup.file_size_human || '—'}</td>
                                        <td>{statusBadge(backup.status)}</td>
                                        <td className="small">{backup.created_at}</td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-2">
                                                {can('admin.backups.show') && (
                                                    <Link
                                                        href={route('admin.backups.show', backup.id)}
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                    >
                                                        تفاصيل
                                                    </Link>
                                                )}
                                                {backup.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-danger"
                                                        onClick={() => setItemToDelete(backup)}
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
