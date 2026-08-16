import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ tags, filters, flash }) {
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        const currentQuery = filters.query || '';
        if (query === currentQuery) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.blog-tags.index'),
                {
                    ...(query ? { query } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [query, filters.query]);

    return (
        <AdminUiPage
            title="وسوم المدونة"
            subtitle="إدارة وسوم المقالات"
            flash={flash}
            actions={
                <div className="d-flex flex-wrap gap-2">
                    {can('admin.blog-tags.edit') && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost"
                            onClick={() =>
                                router.post(
                                    route('admin.blog-tags.update-counts'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <i className="ri-refresh-line" />
                            تحديث العدادات
                        </button>
                    )}
                    {can('admin.blog-tags.create') && (
                        <Link
                            href={route('admin.blog-tags.create')}
                            className="admin-btn admin-btn-primary"
                        >
                            <i className="ri-add-line" />
                            وسم جديد
                        </Link>
                    )}
                </div>
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete ? `هل أنت متأكد من حذف الوسم «${itemToDelete.name}»؟` : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.blog-tags.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة الوسوم" icon="ri-price-tag-3-line">
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
                                        <th>الاسم</th>
                                        <th>المسار</th>
                                        <th>المقالات</th>
                                        <th>الحالة</th>
                                        <th>العمليات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(tags.data || []).map((tag) => (
                                        <tr key={tag.id}>
                                            <td>{tag.id}</td>
                                            <td>
                                                <strong>{tag.name}</strong>
                                            </td>
                                            <td dir="ltr">{tag.slug}</td>
                                            <td>{tag.posts_count ?? 0}</td>
                                            <td>
                                                <span
                                                    className={`admin-badge ${
                                                        tag.is_active
                                                            ? 'admin-badge-success'
                                                            : 'admin-badge-warning'
                                                    }`}
                                                >
                                                    {tag.is_active ? 'نشط' : 'معطّل'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="admin-action-group">
                                                    {can('admin.blog-tags.edit') && (
                                                        <Link
                                                            href={route(
                                                                'admin.blog-tags.edit',
                                                                tag.id,
                                                            )}
                                                            className="admin-action-btn admin-action-edit"
                                                            title="تعديل"
                                                        >
                                                            <i className="ri-edit-line" />
                                                        </Link>
                                                    )}
                                                    {can('admin.blog-tags.delete') && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-delete"
                                                            title="حذف"
                                                            onClick={() => setItemToDelete(tag)}
                                                        >
                                                            <i className="ri-delete-bin-line" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {(tags.data || []).length === 0 && (
                                        <TableEmptyRow colSpan={6}>
                                            لا توجد وسوم.
                                        </TableEmptyRow>
                                    )}
                                </tbody>
                            </table>
                </div>

                {tags.links?.length > 3 && (
                            <div className="admin-table-footer">
                                <div className="admin-table-meta">
                                    عرض {tags.from} إلى {tags.to} من أصل {tags.total}
                                </div>
                                <div className="d-flex gap-2 flex-wrap">
                                    {tags.links.map((link, i) => (
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
