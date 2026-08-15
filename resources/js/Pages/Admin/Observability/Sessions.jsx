import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Sessions({ items, driver, flash }) {
    const [toRevoke, setToRevoke] = useState(null);
    const [processing, setProcessing] = useState(false);

    const rows = items?.data || [];

    return (
        <AdminUiPage
            title="الجلسات النشطة"
            subtitle="من متصل بالنظام الآن، ومن أي جهاز وعنوان"
            flash={flash}
            actions={
                <Link
                    href={route('admin.observability.security')}
                    className="admin-btn admin-btn-ghost"
                >
                    <i className="ri-arrow-right-line" />
                    رجوع للسجل الأمني
                </Link>
            }
        >
            <DeleteConfirmModal
                show={Boolean(toRevoke)}
                message={
                    toRevoke
                        ? `هل تريد إبطال جلسة ${toRevoke.user?.name || 'زائر'}؟ سيُطلب منه الدخول مرة أخرى.`
                        : ''
                }
                processing={processing}
                onConfirm={() => {
                    if (!toRevoke || processing) {
                        return;
                    }
                    setProcessing(true);
                    router.delete(route('admin.observability.sessions.revoke', toRevoke.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setToRevoke(null);
                        },
                    });
                }}
                onCancel={() => !processing && setToRevoke(null)}
            />

            {!items && (
                <div className="admin-alert admin-alert-warning">
                    تخزين الجلسات الحالي هو <code dir="ltr">{driver}</code> — هذه الشاشة تحتاج
                    <code dir="ltr"> SESSION_DRIVER=database </code>
                    لتتمكّن من قراءة الجلسات.
                </div>
            )}

            {items && (
                <AdminPanel title="الجلسات" icon="ri-computer-line">
                    <div className="admin-table-wrap">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>عنوان IP</th>
                                    <th>الجهاز</th>
                                    <th>آخر نشاط</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            {row.user ? (
                                                <span>
                                                    <strong>{row.user.name}</strong>
                                                    <span className="d-block text-muted fs-12" dir="ltr">
                                                        {row.user.email}
                                                    </span>
                                                </span>
                                            ) : (
                                                <span className="text-muted">زائر غير مسجّل</span>
                                            )}
                                            {row.is_current && (
                                                <span className="admin-badge admin-badge-success ms-1">
                                                    جلستك الحالية
                                                </span>
                                            )}
                                        </td>
                                        <td dir="ltr">{row.ip_address || '—'}</td>
                                        <td>
                                            {row.device}
                                            <span
                                                className="d-block text-muted fs-11"
                                                dir="ltr"
                                                title={row.user_agent}
                                            >
                                                {row.user_agent}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="d-block" dir="ltr">
                                                {row.last_activity}
                                            </span>
                                            <span className="text-muted fs-11">
                                                {row.last_activity_human}
                                            </span>
                                        </td>
                                        <td>
                                            {!row.is_current && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-delete"
                                                    title="إبطال الجلسة"
                                                    onClick={() => setToRevoke(row)}
                                                >
                                                    <i className="ri-logout-box-line" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-center text-muted py-4">
                                            لا توجد جلسات نشطة.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination paginator={items} />
                </AdminPanel>
            )}
        </AdminUiPage>
    );
}
