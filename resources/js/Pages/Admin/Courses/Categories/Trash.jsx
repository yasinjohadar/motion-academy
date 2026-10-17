import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Trash({ categories, filters, flash }) {
    const [query, setQuery] = useState(filters.query || '');
    // { type: 'restore' | 'force', category }
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (query === (filters.query || '')) {
            return undefined;
        }
        const timer = setTimeout(() => {
            router.get(route('admin.course-categories.trash'), query ? { query } : {}, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 350);
        return () => clearTimeout(timer);
    }, [query, filters.query]);

    const confirm = () => {
        if (!pending || processing) {
            return;
        }
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };
        if (pending.type === 'restore') {
            router.post(route('admin.course-categories.restore', pending.category.id), {}, options);
        } else {
            router.delete(route('admin.course-categories.force-delete', pending.category.id), options);
        }
    };

    const rows = categories.data || [];

    return (
        <AdminUiPage
            title="سلة محذوفات التصنيفات"
            subtitle="استرجع التصنيفات المحذوفة أو احذفها نهائياً"
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            actions={
                <Link href={route('admin.course-categories.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للتصنيفات
                </Link>
            }
        >
            <DeleteConfirmModal
                show={Boolean(pending)}
                title={pending?.type === 'restore' ? 'تأكيد الاسترجاع' : 'تأكيد الحذف النهائي'}
                confirmLabel={pending?.type === 'restore' ? 'استرجاع' : 'حذف نهائي'}
                message={
                    pending
                        ? pending.type === 'restore'
                            ? `سيعود التصنيف «${pending.category.name}» إلى قائمة التصنيفات.`
                            : `سيتم حذف «${pending.category.name}» نهائياً مع صورته، ولا يمكن التراجع عن ذلك.`
                        : ''
                }
                processing={processing}
                onConfirm={confirm}
                onCancel={() => !processing && setPending(null)}
            />

            <AdminPanel title="التصنيفات المحذوفة" icon="ri-delete-bin-6-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالاسم أو المسار..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التصنيف</th>
                                <th>المسار</th>
                                <th>الأب</th>
                                <th>تاريخ الحذف</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((category) => (
                                <tr key={category.id}>
                                    <td>{category.id}</td>
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <span
                                                className="course-cat-avatar"
                                                style={{ '--cat-color': category.color || '#64748b' }}
                                            >
                                                {category.image_url ? (
                                                    <img src={category.image_url} alt="" />
                                                ) : (
                                                    <i className={category.icon || 'ri-folder-3-line'} />
                                                )}
                                            </span>
                                            <strong>{category.name}</strong>
                                        </div>
                                    </td>
                                    <td dir="ltr" className="text-muted">
                                        {category.slug}
                                    </td>
                                    <td>
                                        {category.parent?.name || '—'}
                                        {category.parent_trashed && (
                                            <span className="admin-badge admin-badge-warning ms-2">
                                                محذوف
                                            </span>
                                        )}
                                    </td>
                                    <td>{category.deleted_at}</td>
                                    <td>
                                        <div className="admin-action-group">
                                            <button
                                                type="button"
                                                className="admin-action-btn admin-action-restore"
                                                title={
                                                    category.parent_trashed
                                                        ? 'استرجع التصنيف الأب أولاً'
                                                        : 'استرجاع'
                                                }
                                                disabled={category.parent_trashed}
                                                onClick={() => setPending({ type: 'restore', category })}
                                            >
                                                <i className="ri-arrow-go-back-line" />
                                            </button>
                                            <button
                                                type="button"
                                                className="admin-action-btn admin-action-delete"
                                                title="حذف نهائي"
                                                onClick={() => setPending({ type: 'force', category })}
                                            >
                                                <i className="ri-delete-bin-line" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={6} icon="ri-delete-bin-6-line">
                                    سلة المحذوفات فارغة.
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={categories} />
            </AdminPanel>
        </AdminUiPage>
    );
}
