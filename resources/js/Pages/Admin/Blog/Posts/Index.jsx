import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const STATUS_LABELS = {
    draft: 'مسودة',
    published: 'منشور',
    scheduled: 'مجدول',
    archived: 'مؤرشف',
};

export default function Index({ posts, categories = [], filters, flash }) {
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [status, setStatus] = useState(filters.status || '');
    const [categoryId, setCategoryId] = useState(
        filters.blog_category_id ? String(filters.blog_category_id) : '',
    );
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        const currentQuery = filters.query || '';
        const currentStatus = filters.status || '';
        const currentCategory = filters.blog_category_id
            ? String(filters.blog_category_id)
            : '';

        if (query === currentQuery && status === currentStatus && categoryId === currentCategory) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.blog-posts.index'),
                {
                    ...(query ? { query } : {}),
                    ...(status ? { status } : {}),
                    ...(categoryId ? { blog_category_id: categoryId } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [query, status, categoryId, filters.query, filters.status, filters.blog_category_id]);

    return (
        <AdminUiPage
            title="مقالات المدونة"
            subtitle="إدارة المقالات والنشر والتمييز"
            flash={flash}
            actions={
                can('admin.blog-posts.create') && (
                    <Link
                        href={route('admin.blog-posts.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-add-line" />
                        مقال جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `هل أنت متأكد من حذف المقال «${itemToDelete.title}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.blog-posts.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة المقالات" icon="ri-article-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                                <div className="search-input-wrap">
                                    <i className="ri-search-line" />
                                    <input
                                        type="search"
                                        className="form-control"
                                        placeholder="بحث بالعنوان أو المحتوى..."
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                    />
                                </div>
                                <select
                                    className="form-select"
                                    style={{ maxWidth: 160 }}
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                >
                                    <option value="">كل الحالات</option>
                                    <option value="draft">مسودة</option>
                                    <option value="published">منشور</option>
                                    <option value="scheduled">مجدول</option>
                                    <option value="archived">مؤرشف</option>
                                </select>
                                <select
                                    className="form-select"
                                    style={{ maxWidth: 200 }}
                                    value={categoryId}
                                    onChange={(e) => setCategoryId(e.target.value)}
                                >
                                    <option value="">كل التصنيفات</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                </div>

                <div className="admin-table-wrap">
                            <table className="admin-data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>العنوان</th>
                                        <th>التصنيف</th>
                                        <th>الحالة</th>
                                        <th>مميز</th>
                                        <th>تاريخ النشر</th>
                                        <th>العمليات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(posts.data || []).map((post) => (
                                        <tr key={post.id}>
                                            <td>{post.id}</td>
                                            <td>
                                                <strong>{post.title}</strong>
                                                {post.author?.name && (
                                                    <div className="small text-muted">
                                                        {post.author.name}
                                                    </div>
                                                )}
                                            </td>
                                            <td>{post.category?.name || '—'}</td>
                                            <td>
                                                <span
                                                    className={`admin-badge ${
                                                        post.status === 'published'
                                                            ? 'admin-badge-success'
                                                            : 'admin-badge-warning'
                                                    }`}
                                                >
                                                    {STATUS_LABELS[post.status] || post.status}
                                                </span>
                                            </td>
                                            <td>
                                                {post.is_featured ? (
                                                    <span className="admin-badge admin-badge-success">
                                                        نعم
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td dir="ltr">{post.published_at || '—'}</td>
                                            <td>
                                                <div className="admin-action-group">
                                                    {post.can?.update && (
                                                        <>
                                                            <Link
                                                                href={route(
                                                                    'admin.blog-posts.edit',
                                                                    post.id,
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
                                                                    post.status === 'published'
                                                                        ? 'إلغاء النشر'
                                                                        : 'نشر'
                                                                }
                                                                onClick={() =>
                                                                    router.post(
                                                                        route(
                                                                            'admin.blog-posts.toggle-publish',
                                                                            post.id,
                                                                        ),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                <i
                                                                    className={
                                                                        post.status === 'published'
                                                                            ? 'ri-eye-off-line'
                                                                            : 'ri-eye-line'
                                                                    }
                                                                />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-edit"
                                                                title={
                                                                    post.is_featured
                                                                        ? 'إلغاء التمييز'
                                                                        : 'تمييز'
                                                                }
                                                                onClick={() =>
                                                                    router.post(
                                                                        route(
                                                                            'admin.blog-posts.toggle-featured',
                                                                            post.id,
                                                                        ),
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                <i
                                                                    className={
                                                                        post.is_featured
                                                                            ? 'ri-star-fill'
                                                                            : 'ri-star-line'
                                                                    }
                                                                />
                                                            </button>
                                                        </>
                                                    )}
                                                    {post.can?.delete && (
                                                        <button
                                                            type="button"
                                                            className="admin-action-btn admin-action-delete"
                                                            title="حذف"
                                                            onClick={() => setItemToDelete(post)}
                                                        >
                                                            <i className="ri-delete-bin-line" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {(posts.data || []).length === 0 && (
                                        <TableEmptyRow colSpan={7}>
                                            لا توجد مقالات.
                                        </TableEmptyRow>
                                    )}
                                </tbody>
                            </table>
                </div>

                {posts.links?.length > 3 && (
                            <div className="admin-table-footer">
                                <div className="admin-table-meta">
                                    عرض {posts.from} إلى {posts.to} من أصل {posts.total}
                                </div>
                                <div className="d-flex gap-2 flex-wrap">
                                    {posts.links.map((link, i) => (
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
