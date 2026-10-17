import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import useCan from '@/hooks/useCan';
import useSortable from '@/hooks/useSortable';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const COLUMNS = 8;

function CategoryAvatar({ category }) {
    return (
        <span className="course-cat-avatar" style={{ '--cat-color': category.color || '#2563eb' }}>
            {category.image_url ? (
                <img src={category.image_url} alt="" />
            ) : (
                <i className={category.icon || 'ri-folder-3-line'} />
            )}
        </span>
    );
}

export default function Index({ tree, categories, roots = [], stats, filters, flash }) {
    const { can } = useCan();
    const canEdit = can('admin.course-categories.edit');
    const canDelete = can('admin.course-categories.delete');
    const isTree = Array.isArray(tree);

    const [query, setQuery] = useState(filters.query || '');
    const [items, setItems] = useState(tree || []);
    const [collapsed, setCollapsed] = useState({});
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    // Resync the local (drag-reordered) copy whenever the server sends a fresh tree.
    const [syncedTree, setSyncedTree] = useState(tree);
    if (tree !== syncedTree) {
        setSyncedTree(tree);
        setItems(tree || []);
    }

    const applyFilters = (next) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, query, ...next }).filter(
                ([, value]) => value !== '' && value != null,
            ),
        );
        router.get(route('admin.course-categories.index'), params, {
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

    const hasFilters = ['query', 'status', 'featured', 'parent'].some((key) => filters[key]);

    /* ---------- drag & drop (siblings only) ---------- */

    // Sibling groups: "root" for top-level categories, "parent-{id}" for a root's children.
    const groupOf = (category) => (category.parent_id ? `parent-${category.parent_id}` : 'root');

    const siblingIds = (category) =>
        (category.parent_id
            ? items.find((root) => root.id === category.parent_id)?.children || []
            : items
        ).map((item) => item.id);

    const sortable = useSortable({
        onReorder: (group, ids) => {
            const sortByIds = (list) => ids.map((id) => list.find((item) => item.id === id));
            const original = items;
            const parentId = group === 'root' ? null : Number(group.replace('parent-', ''));

            setItems(
                parentId == null
                    ? sortByIds(items)
                    : items.map((root) =>
                          root.id === parentId ? { ...root, children: sortByIds(root.children) } : root,
                      ),
            );
            router.post(
                route('admin.course-categories.reorder'),
                { ids },
                { preserveScroll: true, preserveState: true, onError: () => setItems(original) },
            );
        },
    });

    /* ---------- rows ---------- */

    const actions = (category) => (
        <div className="admin-action-group">
            {canEdit && (
                <>
                    <Link
                        href={route('admin.course-categories.edit', category.id)}
                        className="admin-action-btn admin-action-edit"
                        title="تعديل"
                    >
                        <i className="ri-edit-line" />
                    </Link>
                    <button
                        type="button"
                        className={`admin-action-btn ${category.is_featured ? 'admin-action-star-on' : 'admin-action-star-off'}`}
                        title={
                            category.is_featured ? 'مميز — اضغط لإلغاء التمييز' : 'غير مميز — اضغط للتمييز'
                        }
                        onClick={() =>
                            router.post(
                                route('admin.course-categories.toggle-featured', category.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <i className={category.is_featured ? 'ri-star-fill' : 'ri-star-line'} />
                    </button>
                    <button
                        type="button"
                        className={`admin-action-btn ${category.is_active ? 'admin-action-on' : 'admin-action-off'}`}
                        title={category.is_active ? 'نشط — اضغط للتعطيل' : 'معطّل — اضغط للتفعيل'}
                        onClick={() =>
                            router.post(
                                route('admin.course-categories.toggle-active', category.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <i className={category.is_active ? 'ri-toggle-fill' : 'ri-toggle-line'} />
                    </button>
                </>
            )}
            {can('admin.course-categories.create') && !category.parent_id && (
                <Link
                    href={route('admin.course-categories.create', { parent: category.id })}
                    className="admin-action-btn admin-action-add"
                    title="إضافة تصنيف فرعي"
                >
                    <i className="ri-add-line" />
                </Link>
            )}
            {canDelete && (
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
    );

    const row = (category, { isChild = false, draggable = false } = {}) => {
        const childCount = category.children_count ?? category.children?.length ?? 0;
        const isCollapsed = collapsed[category.id];
        const { className: sortClass = '', ...dragProps } = draggable
            ? sortable.itemProps(category.id, groupOf(category), siblingIds(category))
            : {};

        return (
            <tr
                key={category.id}
                className={`course-cat-row${isChild ? ' is-child' : ''} ${sortClass}`.trim()}
                {...dragProps}
            >
                <td className="course-cat-handle-cell">
                    {draggable ? (
                        <span
                            className="course-cat-handle"
                            title="اسحب لإعادة الترتيب"
                            {...sortable.handleProps(category.id, groupOf(category))}
                        >
                            <i className="ri-draggable" />
                        </span>
                    ) : (
                        <span className="text-muted">{category.id}</span>
                    )}
                </td>
                <td>
                    <div className="d-flex align-items-center gap-2">
                        {isChild && <i className="ri-corner-down-left-line text-muted course-cat-branch" />}
                        {isTree && !isChild && (
                            <button
                                type="button"
                                className="course-cat-toggle"
                                disabled={childCount === 0}
                                onClick={() =>
                                    setCollapsed((c) => ({ ...c, [category.id]: !c[category.id] }))
                                }
                                aria-label={isCollapsed ? 'توسيع' : 'طي'}
                            >
                                <i
                                    className={
                                        childCount === 0
                                            ? 'ri-subtract-line'
                                            : isCollapsed
                                              ? 'ri-arrow-left-s-line'
                                              : 'ri-arrow-down-s-line'
                                    }
                                />
                            </button>
                        )}
                        <CategoryAvatar category={category} />
                        <div className="min-w-0">
                            <strong className="d-block">{category.name}</strong>
                            {category.description_excerpt && (
                                <small className="text-muted d-block text-truncate course-cat-desc">
                                    {category.description_excerpt}
                                </small>
                            )}
                        </div>
                        {category.is_featured && (
                            <span className="admin-badge admin-badge-success">مميز</span>
                        )}
                    </div>
                </td>
                <td dir="ltr" className="text-muted">
                    {category.slug}
                </td>
                <td>
                    {category.parent?.name ||
                        (category.parent_id ? '—' : <span className="text-muted">رئيسي</span>)}
                </td>
                <td>{category.parent_id ? '—' : childCount}</td>
                <td>
                    <span className="admin-badge admin-badge-info">{category.courses_count ?? 0}</span>
                </td>
                <td>
                    <span
                        className={`admin-badge ${category.is_active ? 'admin-badge-success' : 'admin-badge-warning'}`}
                    >
                        {category.is_active ? 'نشط' : 'معطّل'}
                    </span>
                </td>
                <td>{actions(category)}</td>
            </tr>
        );
    };

    const treeRows = items.flatMap((root) => [
        row(root, { draggable: canEdit }),
        ...(collapsed[root.id] || sortable.draggingGroup === 'root'
            ? []
            : (root.children || []).map((child) => row(child, { isChild: true, draggable: canEdit }))),
    ]);

    const flatRows = (categories?.data || []).map((category) => row(category));
    const rows = isTree ? treeRows : flatRows;

    return (
        <AdminUiPage
            title="تصنيفات الدورات"
            subtitle="نظّم الدورات في تصنيفات رئيسية وفرعية، ورتّبها بالسحب والإفلات"
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            chips={[
                { label: 'الإجمالي', value: stats.total, icon: 'ri-folders-line' },
                { label: 'رئيسية', value: stats.roots, icon: 'ri-folder-3-line' },
                { label: 'نشطة', value: stats.active, icon: 'ri-checkbox-circle-line' },
                { label: 'مميزة', value: stats.featured, icon: 'ri-star-line' },
            ]}
            actions={
                <>
                    {can('admin.course-categories.restore') && (
                        <Link
                            href={route('admin.course-categories.trash')}
                            className="admin-btn admin-btn-ghost"
                        >
                            <i className="ri-delete-bin-6-line" />
                            سلة المحذوفات
                            {stats.trashed > 0 && (
                                <span className="admin-badge admin-badge-warning ms-1">{stats.trashed}</span>
                            )}
                        </Link>
                    )}
                    {can('admin.course-categories.create') && (
                        <Link
                            href={route('admin.course-categories.create')}
                            className="admin-btn admin-btn-primary"
                        >
                            <i className="ri-add-line" />
                            تصنيف جديد
                        </Link>
                    )}
                </>
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `سيتم نقل التصنيف «${itemToDelete.name}» إلى سلة المحذوفات، ويمكنك استرجاعه لاحقاً.`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.course-categories.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel
                title={isTree ? 'شجرة التصنيفات' : 'نتائج البحث'}
                icon={isTree ? 'ri-node-tree' : 'ri-filter-3-line'}
                action={
                    isTree && canEdit && items.length > 1 ? (
                        <small className="text-muted">
                            <i className="ri-draggable" /> اسحب من المقبض لإعادة الترتيب داخل نفس المستوى
                        </small>
                    ) : null
                }
            >
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالاسم أو المسار أو الوصف..."
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
                            <option value="active">نشط</option>
                            <option value="inactive">معطّل</option>
                        </select>
                        <select
                            className="form-select w-auto"
                            value={filters.featured}
                            onChange={(e) => applyFilters({ featured: e.target.value })}
                        >
                            <option value="">مميز وغير مميز</option>
                            <option value="1">المميزة فقط</option>
                            <option value="0">غير المميزة</option>
                        </select>
                        <select
                            className="form-select w-auto"
                            value={filters.parent}
                            onChange={(e) => applyFilters({ parent: e.target.value })}
                        >
                            <option value="">كل المستويات</option>
                            <option value="root">الرئيسية فقط</option>
                            {roots.map((root) => (
                                <option key={root.id} value={root.id}>
                                    فروع: {root.name}
                                </option>
                            ))}
                        </select>
                        {hasFilters && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                onClick={() => {
                                    setQuery('');
                                    router.get(
                                        route('admin.course-categories.index'),
                                        {},
                                        { preserveScroll: true },
                                    );
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
                                <th style={{ width: 48 }}>{isTree ? '' : '#'}</th>
                                <th>التصنيف</th>
                                <th>المسار</th>
                                <th>الأب</th>
                                <th>الفروع</th>
                                <th>الدورات</th>
                                <th>الحالة</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody
                            className={sortable.isDragging ? 'is-sorting' : undefined}
                            {...sortable.containerProps}
                        >
                            {rows}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={COLUMNS} icon="ri-folder-open-line">
                                    {hasFilters
                                        ? 'لا توجد تصنيفات تطابق البحث.'
                                        : 'لا توجد تصنيفات بعد. ابدأ بإنشاء أول تصنيف.'}
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                {!isTree && <Pagination paginator={categories} />}
            </AdminPanel>
        </AdminUiPage>
    );
}
