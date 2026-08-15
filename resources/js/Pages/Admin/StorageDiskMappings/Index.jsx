import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ mappings, flash }) {
    const { can } = useCan();
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    return (
        <AdminUiPage
            title="ربط الأقراص"
            subtitle="اربط الأقراص المنطقية (مثل site) بإعدادات التخزين السحابي"
            flash={flash}
            actions={
                can('admin.storage-disk-mappings.create') && (
                    <Link
                        href={route('admin.storage-disk-mappings.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-link" />
                        ربط جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `هل أنت متأكد من حذف ربط القرص «${itemToDelete.label}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(
                        route('admin.storage-disk-mappings.destroy', itemToDelete.id),
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setDeleting(false);
                                setItemToDelete(null);
                            },
                        },
                    );
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة روابط الأقراص" icon="ri-link">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>القرص</th>
                                    <th>التسمية</th>
                                    <th>التخزين الأساسي</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {mappings.length === 0 && (
                                    <tr>
                                        <td colSpan="6" className="dashboard-table-empty">
                                            لا توجد روابط أقراص بعد
                                        </td>
                                    </tr>
                                )}
                                {mappings.map((mapping, index) => (
                                    <tr key={mapping.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <code>{mapping.disk_name}</code>
                                        </td>
                                        <td>{mapping.label}</td>
                                        <td>
                                            {mapping.primary_storage ? (
                                                <>
                                                    {mapping.primary_storage.name}
                                                    <div className="text-muted small">
                                                        {
                                                            mapping.primary_storage
                                                                .driver_label
                                                        }
                                                    </div>
                                                </>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td>
                                            <span
                                                className={`admin-badge ${mapping.is_active ? 'admin-badge-success' : 'admin-badge-muted'}`}
                                            >
                                                {mapping.is_active ? 'نشط' : 'موقوف'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-2">
                                                {can(
                                                    'admin.storage-disk-mappings.edit',
                                                ) && (
                                                    <Link
                                                        href={route(
                                                            'admin.storage-disk-mappings.edit',
                                                            mapping.id,
                                                        )}
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                    >
                                                        تعديل
                                                    </Link>
                                                )}
                                                {can(
                                                    'admin.storage-disk-mappings.delete',
                                                ) && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-danger"
                                                        onClick={() =>
                                                            setItemToDelete(mapping)
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
