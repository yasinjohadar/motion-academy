import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ roles, flash }) {
    const [roleToDelete, setRoleToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (!roleToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.roles.destroy', roleToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setRoleToDelete(null);
            },
        });
    };

    return (
        <AdminUiPage
            title="الأدوار والصلاحيات"
            subtitle="إدارة أدوار النظام وتعيين حزم الصلاحيات لكل لوحة"
            flash={flash}
            actions={
                <Link href={route('admin.roles.create')} className="admin-btn admin-btn-primary">
                    <i className="ri-shield-user-line" />
                    إنشاء دور جديد
                </Link>
            }
        >
            <DeleteConfirmModal
                show={Boolean(roleToDelete)}
                message={
                    roleToDelete
                        ? `هل أنت متأكد من رغبتك في حذف الدور «${roleToDelete.name}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={confirmDelete}
                onCancel={() => !deleting && setRoleToDelete(null)}
            />

            <AdminPanel title="قائمة الأدوار" icon="ri-shield-user-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم الدور</th>
                                    <th>الصلاحيات</th>
                                    <th>المستخدمون</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {roles.length === 0 && (
                                    <TableEmptyRow colSpan={5}>
                                        لا توجد أدوار
                                    </TableEmptyRow>
                                )}
                                {roles.map((role, index) => (
                                    <tr key={role.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{role.name}</strong>
                                            {role.is_locked && (
                                                <span className="admin-badge admin-badge-warning ms-2">
                                                    محمي
                                                </span>
                                            )}
                                        </td>
                                        <td>{role.permissions_count}</td>
                                        <td>{role.users_count}</td>
                                        <td>
                                            <div className="admin-action-group">
                                                <Link
                                                    href={route('admin.roles.edit', role.id)}
                                                    className="admin-action-btn admin-action-edit"
                                                    title="تعديل"
                                                >
                                                    <i className="ri-edit-line" />
                                                </Link>
                                                {!role.is_locked && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-delete"
                                                        title="حذف"
                                                        onClick={() => setRoleToDelete(role)}
                                                    >
                                                        <i className="ri-delete-bin-line" />
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
