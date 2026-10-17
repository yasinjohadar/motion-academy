import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import useCan from '@/hooks/useCan';
import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ subscribers, filters, stats, sources, flash }) {
    const { can } = useCan();
    const { errors: pageErrors } = usePage().props;
    const [query, setQuery] = useState(filters.query || '');
    const [showAdd, setShowAdd] = useState(false);
    const [toDelete, setToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const form = useForm({ email: '', name: '' });
    const fieldErrors = { ...pageErrors, ...form.errors };

    const applyFilters = (next) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, query, ...next }).filter(
                ([, value]) => value !== '' && value != null,
            ),
        );
        router.get(route('admin.newsletter.index'), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (query === (filters.query || '')) {
            return undefined;
        }
        const timer = setTimeout(() => applyFilters({ query }), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const hasFilters = Boolean(filters.query || filters.status || filters.source);

    const exportUrl = () => {
        const params = Object.fromEntries(Object.entries(filters).filter(([, value]) => value));
        return route('admin.newsletter.export', params);
    };

    const submitAdd = (e) => {
        e.preventDefault();
        form.post(route('admin.newsletter.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowAdd(false);
            },
        });
    };

    const rate = stats.total > 0 ? Math.round((stats.subscribed / stats.total) * 100) : 0;
    const rows = subscribers.data || [];

    return (
        <AdminUiPage
            title="القائمة البريدية"
            subtitle="المشتركون في النشرة البريدية من الموقع، مع مصدر كل اشتراك وحالته"
            eyebrowIcon="ri-mail-send-line"
            flash={flash}
            chips={[
                { label: 'الإجمالي', value: stats.total, icon: 'ri-group-line' },
                { label: 'مشتركون', value: stats.subscribed, icon: 'ri-checkbox-circle-line' },
                { label: 'ألغوا الاشتراك', value: stats.unsubscribed, icon: 'ri-close-circle-line' },
                { label: 'هذا الشهر', value: stats.this_month, icon: 'ri-calendar-line' },
                { label: 'نسبة النشطين', value: `${rate}%`, icon: 'ri-pie-chart-line' },
            ]}
            actions={
                <>
                    {can('admin.newsletter-subscribers.export') && (
                        <a href={exportUrl()} className="admin-btn admin-btn-ghost">
                            <i className="ri-download-2-line" />
                            تصدير CSV
                        </a>
                    )}
                    {can('admin.newsletter-subscribers.create') && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-primary"
                            onClick={() => setShowAdd((open) => !open)}
                        >
                            <i className={showAdd ? 'ri-close-line' : 'ri-user-add-line'} />
                            {showAdd ? 'إغلاق' : 'إضافة مشترك'}
                        </button>
                    )}
                </>
            }
        >
            <DeleteConfirmModal
                show={Boolean(toDelete)}
                message={toDelete ? `سيتم حذف ${toDelete.email} نهائياً من القائمة البريدية.` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!toDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.newsletter.destroy', toDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setToDelete(null)}
            />

            {showAdd && (
                <AdminPanel title="إضافة مشترك يدوياً" icon="ri-user-add-line" className="mb-4">
                    <form onSubmit={submitAdd} className="row g-3 align-items-end">
                        <div className="col-md-5">
                            <label className="form-label">البريد الإلكتروني *</label>
                            <input
                                type="email"
                                dir="ltr"
                                autoFocus
                                className={`form-control${fieldErrors.email ? ' is-invalid' : ''}`}
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                placeholder="name@example.com"
                            />
                            {fieldErrors.email && <div className="invalid-feedback">{fieldErrors.email}</div>}
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">الاسم (اختياري)</label>
                            <input
                                type="text"
                                className={`form-control${fieldErrors.name ? ' is-invalid' : ''}`}
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                            />
                            {fieldErrors.name && <div className="invalid-feedback">{fieldErrors.name}</div>}
                        </div>
                        <div className="col-md-3">
                            <button
                                type="submit"
                                className="admin-btn admin-btn-primary w-100"
                                disabled={form.processing}
                            >
                                <i className="ri-add-line" />
                                إضافة
                            </button>
                        </div>
                    </form>
                </AdminPanel>
            )}

            <AdminPanel title="المشتركون" icon="ri-mail-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالبريد أو الاسم..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select w-auto"
                            value={filters.status}
                            onChange={(e) => applyFilters({ status: e.target.value })}
                        >
                            <option value="">كل الحالات</option>
                            <option value="subscribed">مشترك</option>
                            <option value="unsubscribed">ألغى الاشتراك</option>
                        </select>
                        <select
                            className="form-select w-auto"
                            value={filters.source}
                            onChange={(e) => applyFilters({ source: e.target.value })}
                        >
                            <option value="">كل المصادر</option>
                            {sources.map((source) => (
                                <option key={source.value} value={source.value}>
                                    {source.label} ({source.count})
                                </option>
                            ))}
                        </select>
                        {hasFilters && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                onClick={() => {
                                    setQuery('');
                                    router.get(route('admin.newsletter.index'), {}, { preserveScroll: true });
                                }}
                            >
                                <i className="ri-refresh-line" />
                                إعادة تعيين
                            </button>
                        )}
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>البريد الإلكتروني</th>
                                <th>المصدر</th>
                                <th>الحالة</th>
                                <th>تاريخ الاشتراك</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((subscriber) => (
                                <tr key={subscriber.id}>
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <span className="nl-admin-avatar">
                                                {subscriber.email.charAt(0).toUpperCase()}
                                            </span>
                                            <div className="min-w-0">
                                                <strong
                                                    className="d-block"
                                                    dir="ltr"
                                                    style={{ textAlign: 'right' }}
                                                >
                                                    {subscriber.email}
                                                </strong>
                                                {subscriber.name && (
                                                    <small className="text-muted">{subscriber.name}</small>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span className="admin-badge admin-badge-info">
                                            {subscriber.source_label}
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            className={`admin-badge ${subscriber.is_subscribed ? 'admin-badge-success' : 'admin-badge-muted'}`}
                                        >
                                            {subscriber.is_subscribed ? 'مشترك' : 'ألغى الاشتراك'}
                                        </span>
                                        {!subscriber.is_subscribed && subscriber.unsubscribed_at && (
                                            <small className="d-block text-muted mt-1">
                                                {subscriber.unsubscribed_at}
                                            </small>
                                        )}
                                    </td>
                                    <td className="text-muted">
                                        {subscriber.subscribed_at || subscriber.created_at}
                                    </td>
                                    <td>
                                        <div className="admin-action-group">
                                            {can('admin.newsletter-subscribers.edit') && (
                                                <button
                                                    type="button"
                                                    className={`admin-action-btn ${subscriber.is_subscribed ? 'admin-action-on' : 'admin-action-off'}`}
                                                    title={
                                                        subscriber.is_subscribed
                                                            ? 'مشترك — اضغط لإلغاء الاشتراك'
                                                            : 'غير مشترك — اضغط للتفعيل'
                                                    }
                                                    onClick={() =>
                                                        router.post(
                                                            route('admin.newsletter.toggle', subscriber.id),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <i
                                                        className={
                                                            subscriber.is_subscribed
                                                                ? 'ri-notification-3-line'
                                                                : 'ri-notification-off-line'
                                                        }
                                                    />
                                                </button>
                                            )}
                                            {can('admin.newsletter-subscribers.delete') && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-delete"
                                                    title="حذف"
                                                    onClick={() => setToDelete(subscriber)}
                                                >
                                                    <i className="ri-delete-bin-line" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={5} icon="ri-mail-line">
                                    {hasFilters
                                        ? 'لا يوجد مشتركون يطابقون البحث.'
                                        : 'لا يوجد مشتركون بعد. ستظهر هنا كل اشتراكات النشرة البريدية من الموقع.'}
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={subscribers} />
            </AdminPanel>
        </AdminUiPage>
    );
}
