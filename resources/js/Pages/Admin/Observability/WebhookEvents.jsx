import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import Pagination from '@/Components/Admin/Pagination';
import { Link, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';

export default function WebhookEvents({ items, filters, types = [], flash }) {
    const [expanded, setExpanded] = useState(null);

    const rows = items?.data || [];

    const applyFilter = (patch) =>
        router.get(
            route('admin.observability.webhooks'),
            {
                ...(filters.state ? { state: filters.state } : {}),
                ...(filters.event_type ? { event_type: filters.event_type } : {}),
                ...patch,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );

    return (
        <AdminUiPage
            title="أحداث Webhook"
            subtitle="الأحداث الواردة من واتساب Evolution وحالة معالجتها"
            chips={[{ label: 'إجمالي', value: items.total, icon: 'ri-webhook-line' }]}
            flash={flash}
            actions={
                <Link
                    href={route('admin.observability.index')}
                    className="admin-btn admin-btn-ghost"
                >
                    <i className="ri-arrow-right-line" />
                    رجوع للمراقبة
                </Link>
            }
        >
            <AdminPanel title="سجل الأحداث" icon="ri-webhook-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <select
                            className="form-select w-auto"
                            value={filters.state || ''}
                            onChange={(e) => applyFilter({ state: e.target.value || undefined })}
                        >
                            <option value="">كل الحالات</option>
                            <option value="unprocessed">غير معالج</option>
                            <option value="processed">معالج</option>
                        </select>
                        {types.length > 0 && (
                            <select
                                className="form-select w-auto"
                                value={filters.event_type || ''}
                                onChange={(e) =>
                                    applyFilter({ event_type: e.target.value || undefined })
                                }
                            >
                                <option value="">كل الأنواع</option>
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>الرقم</th>
                                <th>الحالة</th>
                                <th>وقت الوصول</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <Fragment key={row.id}>
                                    <tr>
                                        <td dir="ltr">
                                            <strong>{row.event_type || '—'}</strong>
                                        </td>
                                        <td dir="ltr">{row.instance || '—'}</td>
                                        <td>
                                            <span
                                                className={`admin-badge ${
                                                    row.is_processed
                                                        ? 'admin-badge-success'
                                                        : 'admin-badge-warning'
                                                }`}
                                            >
                                                {row.is_processed ? 'معالج' : 'غير معالج'}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="d-block" dir="ltr">
                                                {row.created_at}
                                            </span>
                                            <span className="text-muted fs-11">
                                                {row.created_at_human}
                                            </span>
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="admin-action-btn"
                                                title={expanded === row.id ? 'إخفاء الحمولة' : 'عرض الحمولة'}
                                                onClick={() =>
                                                    setExpanded(expanded === row.id ? null : row.id)
                                                }
                                            >
                                                <i className="ri-braces-line" />
                                            </button>
                                        </td>
                                    </tr>
                                    {expanded === row.id && (
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
                                                    {row.payload}
                                                </pre>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-center text-muted py-4">
                                        لا توجد أحداث.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={items} />
            </AdminPanel>
        </AdminUiPage>
    );
}
