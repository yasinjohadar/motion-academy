import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Index({ pages, filters, flash }) {
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [status, setStatus] = useState(filters.status || '');
    const [pageToDelete, setPageToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        const currentQuery = filters.query || '';
        const currentStatus = filters.status || '';
        if (query === currentQuery && status === currentStatus) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.site-pages.index'),
                {
                    ...(query ? { query } : {}),
                    ...(status ? { status } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [query, status, filters.query, filters.status]);

    return (
        <AdminUiPage
            title="صفحات الموقع"
            subtitle="إدارة صفحات مثل من نحن، الخصوصية، والشروط"
            flash={flash}
            actions={
                can('admin.site-pages.create') && (
                    <Link
                        href={route('admin.site-pages.create')}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-file-add-line" />
                        صفحة جديدة
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(pageToDelete)}
                message={
                    pageToDelete
                        ? `هل أنت متأكد من حذف الصفحة «${pageToDelete.title}»؟`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!pageToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.site-pages.destroy', pageToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setPageToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setPageToDelete(null)}
            />

            <AdminPanel title="قائمة الصفحات" icon="ri-pages-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالعنوان أو المسار..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select"
                            style={{ maxWidth: 180 }}
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">كل الحالات</option>
                            <option value="published">منشورة</option>
                            <option value="draft">مسودة</option>
                        </select>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العنوان</th>
                                    <th>المسار</th>
                                    <th>الحالة</th>
                                    <th>آخر تحديث</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(pages.data || []).map((page) => (
                                    <tr key={page.id}>
                                        <td>{page.id}</td>
                                        <td>
                                            <strong>{page.title}</strong>
                                            {page.excerpt && (
                                                <div className="small text-muted">
                                                    {page.excerpt}
                                                </div>
                                            )}
                                        </td>
                                        <td dir="ltr">/{page.slug}</td>
                                        <td>
                                            <span
                                                className={`badge ${page.is_published ? 'bg-success' : 'bg-secondary'}`}
                                            >
                                                {page.is_published ? 'منشورة' : 'مسودة'}
                                            </span>
                                        </td>
                                        <td dir="ltr">{page.updated_at}</td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-1">
                                                {page.can?.update && (
                                                    <>
                                                        <Link
                                                            href={route('admin.site-pages.edit', page.id)}
                                                            className="btn btn-sm btn-outline-primary"
                                                        >
                                                            تعديل
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-secondary"
                                                            onClick={() =>
                                                                router.post(
                                                                    route(
                                                                        'admin.site-pages.toggle-publish',
                                                                        page.id,
                                                                    ),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {page.is_published ? 'إلغاء النشر' : 'نشر'}
                                                        </button>
                                                    </>
                                                )}
                                                {page.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger"
                                                        onClick={() => setPageToDelete(page)}
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {(pages.data || []).length === 0 && (
                                    <TableEmptyRow colSpan={6}>
                                        لا توجد صفحات.
                                    </TableEmptyRow>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </AdminPanel>
        </AdminUiPage>
    );
}
