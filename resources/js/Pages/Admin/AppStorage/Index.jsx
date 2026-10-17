import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ configs, flash }) {
    const { can } = useCan();
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    return (
        <AdminUiPage
            title="التخزين السحابي"
            subtitle="إعدادات Amazon S3 و Cloudflare R2 و IDrive e2 والتخزين المحلي"
            flash={flash}
            actions={
                can('admin.app-storage.create') && (
                    <Link href={route('admin.app-storage.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-cloud-line" />
                        إعداد جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={itemToDelete ? `هل أنت متأكد من حذف إعداد التخزين «${itemToDelete.name}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.app-storage.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة إعدادات التخزين" icon="ri-cloud-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>النوع</th>
                                    <th>الأولوية</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {configs.length === 0 && (
                                    <TableEmptyRow colSpan={6}>لا توجد إعدادات تخزين بعد</TableEmptyRow>
                                )}
                                {configs.map((config, index) => (
                                    <tr key={config.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{config.name}</strong>
                                            {config.cdn_url && (
                                                <div className="text-muted small">{config.cdn_url}</div>
                                            )}
                                        </td>
                                        <td>{config.driver_label}</td>
                                        <td>{config.priority}</td>
                                        <td>
                                            <span
                                                className={`admin-badge ${config.is_active ? 'admin-badge-success' : 'admin-badge-muted'}`}
                                            >
                                                {config.is_active ? 'نشط' : 'موقوف'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-2">
                                                {can('admin.app-storage.test') && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                        onClick={() =>
                                                            router.post(
                                                                route('admin.app-storage.test', config.id),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        اختبار
                                                    </button>
                                                )}
                                                {config.can?.update && (
                                                    <Link
                                                        href={route('admin.app-storage.edit', config.id)}
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                    >
                                                        تعديل
                                                    </Link>
                                                )}
                                                {config.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-danger"
                                                        onClick={() => setItemToDelete(config)}
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
