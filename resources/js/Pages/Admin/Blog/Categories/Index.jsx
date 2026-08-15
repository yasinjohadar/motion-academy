import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ categories, filters, flash }) {
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
                route('admin.blog-categories.index'),
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
            title="تصنيفات المدونة"
            subtitle="إدارة تصنيفات المقالات والسيو الأساسي"
            flash={flash}
            actions={
                can('admin.blog-categories.create') && (
                    <Link
                        href={route('admin.blog-categories.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-add-line" />
                        تصنيف جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `هل أنت متأكد من حذف التصنيف «${itemToDelete.name}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.blog-categories.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة التصنيفات" icon="ri-folder-line">
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
                                        <th>الأب</th>
                                        <th>المقالات</th>
                                        <th>الحالة</th>
                                        <th>العمليات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(categories.data || []).map((category) => (
                                        <tr key={category.id}>
                                            <td>{category.id}</td>
                                            <td>
                                                <strong>{category.name}</strong>
                                                {category.is_featured && (
                                                    <span className="admin-badge admin-badge-success ms-2">
                                                        مميز
                                                    </span>
                                                )}
                                            </td>
                                            <td dir="ltr">{category.slug}</td>
                                            <td>{category.parent?.name || '—'}</td>
                                            <td>{category.posts_count ?? 0}</td>
                                            <td>
                                                <span
                                                    className={`admin-badge ${
                                                        category.is_active
                                                            ? 'admin-badge-success'
                                                            : 'admin-badge-warning'
                                                    }`}
                                                >
                                                    {category.is_active ? 'نشط' : 'معطّل'}
                                                </span>
                                            </td>
                                            <td>
                                                <div className="admin-action-group">
                                                    {can('admin.blog-categories.edit') && (
                                                        <>
                                                            <Link
                                                                href={route(
                                                                    'admin.blog-categories.edit',
                                                                    category.id,
                                                                )}
                                                                className="admin-action-btn admin-action-edit"
                                                                title="تعديل"
                                                            >
                                                                <i className="ri-edit-line" />
                                                            </Link>
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-edit"
                                                                title={
                                                                    category.is_active
                                                                        ? 'تعطيل'
                                                                        : 'تفعيل'
                                                                }
                                                                onClick={() =>
                                                                    router.post(
                                                                        route(
                                                                            'admin.blog-categories.toggle-active',
                                                                            category.id,
                                                                        ),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                <i
                                                                    className={
                                                                        category.is_active
                                                                            ? 'ri-toggle-fill'
                                                                            : 'ri-toggle-line'
                                                                    }
                                                                />
                                                            </button>
                                                        </>
                                                    )}
                                                    {can('admin.blog-categories.delete') && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-delete"
                                                            title="حذف"
                                                            onClick={() => setItemToDelete(category)}
                                                        >
                                                            <i className="ri-delete-bin-line" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {(categories.data || []).length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="text-center text-muted py-4">
                                                لا توجد تصنيفات.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                </div>

                {categories.links?.length > 3 && (
                            <div className="admin-table-footer">
                                <div className="admin-table-meta">
                                    عرض {categories.from} إلى {categories.to} من أصل{' '}
                                    {categories.total}
                                </div>
                                <div className="d-flex gap-2 flex-wrap">
                                    {categories.links.map((link, i) => (
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
