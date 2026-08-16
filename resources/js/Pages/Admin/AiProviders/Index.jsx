import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ providers, flash }) {
    const { can } = useCan();
    const [providerToDelete, setProviderToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [testingId, setTestingId] = useState(null);

    const confirmDelete = () => {
        if (!providerToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.ai-providers.destroy', providerToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setProviderToDelete(null);
            },
        });
    };

    const runTest = (provider) => {
        setTestingId(provider.id);
        router.post(
            route('admin.ai-providers.test', provider.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTestingId(null),
            },
        );
    };

    return (
        <AdminUiPage
            title="مزودو الذكاء الاصطناعي"
            subtitle="إدارة مفاتيح المزودين ديناميكياً واختبار الاتصال قبل الاعتماد"
            flash={flash}
            actions={
                can('admin.ai-providers.create') && (
                    <Link
                        href={route('admin.ai-providers.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-add-line" />
                        مزود جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(providerToDelete)}
                message={
                    providerToDelete
                        ? `هل أنت متأكد من حذف المزود «${providerToDelete.name}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={confirmDelete}
                onCancel={() => !deleting && setProviderToDelete(null)}
            />

            <AdminPanel title="قائمة المزودين" icon="ri-robot-2-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>المزود</th>
                                    <th>النموذج</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {providers.length === 0 && (
                                    <TableEmptyRow colSpan={6}>
                                        لا توجد مزودات بعد — أضف مزوداً واختبر الاتصال
                                    </TableEmptyRow>
                                )}

                                {providers.map((provider, index) => (
                                    <tr key={provider.id}>
                                        <th className="row-number">{index + 1}</th>
                                        <td>
                                            <strong>{provider.name}</strong>
                                            {provider.is_default && (
                                                <span className="admin-badge admin-badge-success ms-2">
                                                    افتراضي
                                                </span>
                                            )}
                                            {!provider.has_api_key &&
                                                provider.driver !== 'ollama' && (
                                                    <span className="admin-badge admin-badge-warning ms-2">
                                                        بدون مفتاح
                                                    </span>
                                                )}
                                        </td>
                                        <td dir="ltr">{provider.driver_label}</td>
                                        <td dir="ltr">{provider.default_model || '—'}</td>
                                        <td>
                                            <span
                                                className={`admin-badge ${
                                                    provider.is_active
                                                        ? 'admin-badge-success'
                                                        : 'admin-badge-warning'
                                                }`}
                                            >
                                                {provider.is_active ? 'نشط' : 'معطّل'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="admin-action-group">
                                                {can('admin.ai-providers.test') && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-edit"
                                                            title="اختبار الاتصال"
                                                            disabled={testingId === provider.id}
                                                            onClick={() => runTest(provider)}
                                                        >
                                                            <i
                                                                className={
                                                                    testingId === provider.id
                                                                        ? 'ri-loader-4-line'
                                                                        : 'ri-wifi-line'
                                                                }
                                                            />
                                                        </button>
                                                        {!provider.is_default && (
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-edit"
                                                                title="تعيين كافتراضي"
                                                                onClick={() =>
                                                                    router.post(
                                                                        route(
                                                                            'admin.ai-providers.set-default',
                                                                            provider.id,
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
                                                                provider.is_active
                                                                    ? 'تعطيل'
                                                                    : 'تفعيل'
                                                            }
                                                            onClick={() =>
                                                                router.post(
                                                                    route(
                                                                        'admin.ai-providers.toggle-active',
                                                                        provider.id,
                                                                    ),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            <i
                                                                className={
                                                                    provider.is_active
                                                                        ? 'ri-toggle-fill'
                                                                        : 'ri-toggle-line'
                                                                }
                                                            />
                                                        </button>
                                                    </>
                                                )}
                                                {can('admin.ai-providers.edit') && (
                                                    <Link
                                                        href={route(
                                                            'admin.ai-providers.edit',
                                                            provider.id,
                                                        )}
                                                        className="admin-action-btn admin-action-edit"
                                                        title="تعديل"
                                                    >
                                                        <i className="ri-edit-line" />
                                                    </Link>
                                                )}
                                                {can('admin.ai-providers.delete') &&
                                                    !provider.is_default && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-delete"
                                                            title="حذف"
                                                            onClick={() =>
                                                                setProviderToDelete(provider)
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
