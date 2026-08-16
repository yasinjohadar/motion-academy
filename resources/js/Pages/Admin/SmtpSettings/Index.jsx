import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ settings, flash }) {
    const { can } = useCan();
    const [settingToDelete, setSettingToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (!settingToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.smtp-settings.destroy', settingToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setSettingToDelete(null);
            },
        });
    };

    const sendTest = (setting) => {
        const to = window.prompt('أدخل بريد المستلم لاختبار الإرسال:', setting.from_address || '');
        if (!to) {
            return;
        }

        router.post(
            route('admin.smtp-settings.test', setting.id),
            { to },
            { preserveScroll: true },
        );
    };

    return (
        <AdminUiPage
            title="إعدادات SMTP"
            subtitle="إدارة ملفات البريد وتعيين الإعداد الافتراضي للنظام"
            flash={flash}
            actions={
                can('admin.smtp-settings.create') && (
                    <Link
                        href={route('admin.smtp-settings.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-mail-add-line" />
                        إعداد جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(settingToDelete)}
                message={
                    settingToDelete
                        ? `هل أنت متأكد من حذف إعداد SMTP «${settingToDelete.name}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={confirmDelete}
                onCancel={() => !deleting && setSettingToDelete(null)}
            />

            <AdminPanel title="قائمة إعدادات SMTP" icon="ri-mail-settings-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>المضيف</th>
                                    <th>المرسل</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {settings.length === 0 && (
                                    <TableEmptyRow colSpan={6}>
                                        لا توجد إعدادات SMTP بعد
                                    </TableEmptyRow>
                                )}

                                {settings.map((setting, index) => (
                                    <tr key={setting.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{setting.name}</strong>
                                            {setting.is_default && (
                                                <span className="admin-badge admin-badge-success ms-2">
                                                    افتراضي
                                                </span>
                                            )}
                                        </td>
                                        <td dir="ltr">
                                            {setting.host}:{setting.port} ({setting.encryption})
                                        </td>
                                        <td>
                                            <div>{setting.from_name}</div>
                                            <small className="text-muted" dir="ltr">
                                                {setting.from_address}
                                            </small>
                                        </td>
                                        <td>
                                            <span
                                                className={`admin-badge ${
                                                    setting.is_active
                                                        ? 'admin-badge-success'
                                                        : 'admin-badge-warning'
                                                }`}
                                            >
                                                {setting.is_active ? 'نشط' : 'معطّل'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="admin-action-group">
                                                {can('admin.smtp-settings.test') && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-edit"
                                                            title="إرسال اختبار"
                                                            onClick={() => sendTest(setting)}
                                                        >
                                                            <i className="ri-send-plane-line" />
                                                        </button>
                                                        {!setting.is_default && (
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-edit"
                                                                title="تعيين كافتراضي"
                                                                onClick={() =>
                                                                    router.post(
                                                                        route(
                                                                            'admin.smtp-settings.set-default',
                                                                            setting.id,
                                                                        ),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                <i className="ri-star-line" />
                                                            </button>
                                                        )}
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-edit"
                                                            title={
                                                                setting.is_active
                                                                    ? 'تعطيل'
                                                                    : 'تفعيل'
                                                            }
                                                            onClick={() =>
                                                                router.post(
                                                                    route(
                                                                        'admin.smtp-settings.toggle-active',
                                                                        setting.id,
                                                                    ),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <i
                                                                className={
                                                                    setting.is_active
                                                                        ? 'ri-toggle-fill'
                                                                        : 'ri-toggle-line'
                                                                }
                                                            />
                                                        </button>
                                                    </>
                                                )}
                                                {can('admin.smtp-settings.edit') && (
                                                    <Link
                                                        href={route(
                                                            'admin.smtp-settings.edit',
                                                            setting.id,
                                                        )}
                                                        className="admin-action-btn admin-action-edit"
                                                        title="تعديل"
                                                    >
                                                        <i className="ri-edit-line" />
                                                    </Link>
                                                )}
                                                {can('admin.smtp-settings.delete') &&
                                                    !setting.is_default && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-delete"
                                                            title="حذف"
                                                            onClick={() =>
                                                                setSettingToDelete(setting)
                                                            }
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
