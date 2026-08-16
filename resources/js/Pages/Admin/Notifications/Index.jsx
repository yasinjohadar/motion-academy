import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ items, filters, categories = [], levels = [], flash }) {
    const { notifications } = usePage().props;
    const unreadCount = notifications?.unread_count ?? 0;

    const [category, setCategory] = useState(filters.category || '');
    const [level, setLevel] = useState(filters.level || '');
    const [state, setState] = useState(filters.state || '');
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [clearingAll, setClearingAll] = useState(false);

    useEffect(() => {
        const unchanged =
            category === (filters.category || '') &&
            level === (filters.level || '') &&
            state === (filters.state || '');

        if (unchanged) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.notifications.index'),
                {
                    ...(category ? { category } : {}),
                    ...(level ? { level } : {}),
                    ...(state ? { state } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [category, level, state, filters.category, filters.level, filters.state]);

    const rows = items.data || [];

    return (
        <AdminUiPage
            title="الإشعارات"
            subtitle="كل الإشعارات الواردة إليك"
            chips={[{ label: 'غير مقروء', value: unreadCount, icon: 'ri-notification-badge-line' }]}
            flash={flash}
            actions={
                <div className="d-flex flex-wrap gap-2">
                    <Link
                        href={route('admin.notifications.preferences')}
                        className="admin-btn admin-btn-ghost"
                    >
                        <i className="ri-equalizer-line" />
                        تفضيلات الإشعارات
                    </Link>
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost"
                            onClick={() =>
                                router.post(
                                    route('admin.notifications.read-all'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <i className="ri-check-double-line" />
                            تحديد الكل كمقروء
                        </button>
                    )}
                    {items.total > 0 && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-danger"
                            onClick={() => setClearingAll(true)}
                        >
                            <i className="ri-delete-bin-line" />
                            حذف الكل
                        </button>
                    )}
                </div>
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={itemToDelete ? `هل أنت متأكد من حذف الإشعار «${itemToDelete.title}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.notifications.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <DeleteConfirmModal
                show={clearingAll}
                message="هل أنت متأكد من حذف جميع إشعاراتك؟ لا يمكن التراجع عن هذه العملية."
                processing={deleting}
                onConfirm={() => {
                    if (deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.notifications.destroy-all'), {
                        onFinish: () => {
                            setDeleting(false);
                            setClearingAll(false);
                        },
                    });
                }}
                onCancel={() => !deleting && setClearingAll(false)}
            />

            <AdminPanel title="سجل الإشعارات" icon="ri-notification-3-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <select
                            className="form-select w-auto"
                            value={category}
                            onChange={(e) => setCategory(e.target.value)}
                        >
                            <option value="">كل الفئات</option>
                            {categories.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className="form-select w-auto"
                            value={level}
                            onChange={(e) => setLevel(e.target.value)}
                        >
                            <option value="">كل المستويات</option>
                            {levels.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className="form-select w-auto"
                            value={state}
                            onChange={(e) => setState(e.target.value)}
                        >
                            <option value="">مقروء وغير مقروء</option>
                            <option value="unread">غير مقروء فقط</option>
                            <option value="read">مقروء فقط</option>
                        </select>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>الإشعار</th>
                                <th>الفئة</th>
                                <th>المستوى</th>
                                <th>التاريخ</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((item) => (
                                <tr key={item.id} className={item.is_read ? '' : 'fw-semibold'}>
                                    <td>
                                        <div className="d-flex align-items-start gap-2">
                                            <span className="avatar avatar-sm bg-primary-transparent flex-shrink-0">
                                                <i className={item.icon} />
                                            </span>
                                            <div>
                                                <strong className="d-block">{item.title}</strong>
                                                <span className="text-muted fs-12">
                                                    {item.message}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {item.category_label && (
                                            <span
                                                className={`admin-badge ${item.category_badge || ''}`}
                                            >
                                                {item.category_label}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <span className={`admin-badge ${item.level_badge || ''}`}>
                                            {item.level_label}
                                        </span>
                                    </td>
                                    <td>
                                        <span className="d-block" dir="ltr">
                                            {item.created_at}
                                        </span>
                                        <span className="text-muted fs-11">
                                            {item.created_at_human}
                                        </span>
                                    </td>
                                    <td>
                                        <div className="admin-action-group">
                                            {item.action_url && (
                                                <a
                                                    href={item.action_url}
                                                    className="admin-action-btn admin-action-edit"
                                                    title="فتح"
                                                >
                                                    <i className="ri-external-link-line" />
                                                </a>
                                            )}
                                            {!item.is_read && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn"
                                                    title="تحديد كمقروء"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'admin.notifications.read',
                                                                item.id,
                                                            ),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <i className="ri-check-line" />
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                className="admin-action-btn admin-action-delete"
                                                title="حذف"
                                                onClick={() => setItemToDelete(item)}
                                            >
                                                <i className="ri-delete-bin-line" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={5}>
                                    لا توجد إشعارات.
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                {items.links?.length > 3 && (
                    <div className="admin-table-footer">
                        <div className="admin-table-meta">
                            عرض {items.from} إلى {items.to} من أصل {items.total}
                        </div>
                        <div className="d-flex gap-2 flex-wrap">
                            {items.links.map((link, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    className={`admin-btn ${link.active ? 'admin-btn-primary' : 'admin-btn-ghost'}`}
                                    disabled={!link.url}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                    onClick={() => link.url && router.get(link.url)}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </AdminPanel>
        </AdminUiPage>
    );
}
