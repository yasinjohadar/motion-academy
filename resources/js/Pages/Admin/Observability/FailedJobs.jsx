import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import { Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';

export default function FailedJobs({ items, filters, queues = [], queue, flash }) {
    const [toForget, setToForget] = useState(null);
    const [flushing, setFlushing] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [expanded, setExpanded] = useState(null);

    const rows = items?.data || [];

    const post = (url) => router.post(url, {}, { preserveScroll: true });

    return (
        <AdminUiPage
            title="المهام الفاشلة"
            subtitle="المهام التي فشلت في قوائم الانتظار وسبب فشلها"
            chips={[{ label: 'إجمالي', value: items.total, icon: 'ri-error-warning-line' }]}
            flash={flash}
            actions={
                <div className="d-flex flex-wrap gap-2">
                    {items.total > 0 && (
                        <>
                            <button
                                type="button"
                                className="admin-btn admin-btn-primary"
                                onClick={() => post(route('admin.observability.failed-jobs.retry-all'))}
                            >
                                <i className="ri-refresh-line" />
                                إعادة محاولة الكل
                            </button>
                            <button
                                type="button"
                                className="admin-btn admin-btn-danger"
                                onClick={() => setFlushing(true)}
                            >
                                <i className="ri-delete-bin-line" />
                                حذف الكل
                            </button>
                        </>
                    )}
                    <Link
                        href={route('admin.observability.index')}
                        className="admin-btn admin-btn-ghost"
                    >
                        <i className="ri-arrow-right-line" />
                        رجوع للمراقبة
                    </Link>
                </div>
            }
        >
            <DeleteConfirmModal
                show={Boolean(toForget)}
                message={toForget ? `هل تريد حذف المهمة «${toForget.job_label}» نهائياً؟` : ''}
                processing={processing}
                onConfirm={() => {
                    if (!toForget || processing) {
                        return;
                    }
                    setProcessing(true);
                    router.delete(route('admin.observability.failed-jobs.forget', toForget.uuid), {
                        preserveScroll: true,
                        onFinish: () => {
                            setProcessing(false);
                            setToForget(null);
                        },
                    });
                }}
                onCancel={() => !processing && setToForget(null)}
            />

            <DeleteConfirmModal
                show={flushing}
                message="هل تريد حذف جميع المهام الفاشلة نهائياً؟ لا يمكن التراجع."
                processing={processing}
                onConfirm={() => {
                    if (processing) {
                        return;
                    }
                    setProcessing(true);
                    router.delete(route('admin.observability.failed-jobs.flush'), {
                        onFinish: () => {
                            setProcessing(false);
                            setFlushing(false);
                        },
                    });
                }}
                onCancel={() => !processing && setFlushing(false)}
            />

            {queue?.needs_worker && (
                <div className="admin-alert admin-alert-info">
                    إعادة المحاولة تُعيد المهمة إلى القائمة فقط — يجب أن يكون العامل يعمل
                    بالطوابير الصحيحة لتُنفَّذ فعلاً:
                    <code className="d-block mt-2" dir="ltr">
                        {queue.worker_command}
                    </code>
                </div>
            )}

            <AdminPanel title="سجل الفشل" icon="ri-error-warning-line">
                {queues.length > 1 && (
                    <div className="card-toolbar mb-3">
                        <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                            <select
                                className="form-select w-auto"
                                value={filters.queue || ''}
                                onChange={(e) =>
                                    router.get(
                                        route('admin.observability.failed-jobs'),
                                        e.target.value ? { queue: e.target.value } : {},
                                        { preserveState: true, replace: true, preserveScroll: true },
                                    )
                                }
                            >
                                <option value="">كل الطوابير</option>
                                {queues.map((name) => (
                                    <option key={name} value={name}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>المهمة</th>
                                <th>الطابور</th>
                                <th>سبب الفشل</th>
                                <th>وقت الفشل</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <Fragment key={row.uuid}>
                                    <tr>
                                        <td>
                                            <strong>{row.job_label}</strong>
                                            <span className="d-block text-muted fs-11" dir="ltr">
                                                {row.job}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="admin-badge admin-badge-accent">
                                                {row.queue}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="fs-12" dir="auto">
                                                {row.exception_summary}
                                            </span>
                                        </td>
                                        <td dir="ltr">{row.failed_at}</td>
                                        <td>
                                            <div className="admin-action-group">
                                                <button
                                                    type="button"
                                                    className="admin-action-btn"
                                                    title={expanded === row.uuid ? 'إخفاء التفاصيل' : 'عرض التفاصيل'}
                                                    onClick={() =>
                                                        setExpanded(expanded === row.uuid ? null : row.uuid)
                                                    }
                                                >
                                                    <i className="ri-file-search-line" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-edit"
                                                    title="إعادة المحاولة"
                                                    onClick={() =>
                                                        post(
                                                            route(
                                                                'admin.observability.failed-jobs.retry',
                                                                row.uuid,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <i className="ri-refresh-line" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-delete"
                                                    title="حذف"
                                                    onClick={() => setToForget(row)}
                                                >
                                                    <i className="ri-delete-bin-line" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    {expanded === row.uuid && (
                                        <tr>
                                            <td colSpan={5}>
                                                <pre
                                                    className="fs-11 mb-0 p-3 rounded"
                                                    dir="ltr"
                                                    style={{
                                                        maxHeight: 320,
                                                        overflow: 'auto',
                                                        whiteSpace: 'pre-wrap',
                                                        background: 'var(--admin-code-bg, rgba(0,0,0,.04))',
                                                    }}
                                                >
                                                    {row.exception}
                                                </pre>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={5}>
                                    لا توجد مهام فاشلة — كل شيء يعمل.
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={items} />
            </AdminPanel>
        </AdminUiPage>
    );
}
