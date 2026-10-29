import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { formatDuration, formatPrice } from '@/Components/Admin/courseFormat';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import useCan from '@/hooks/useCan';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const FILTER_KEYS = ['query', 'category', 'instructor', 'status', 'level', 'pricing', 'featured'];

function PriceCell({ course }) {
    if (course.is_free) {
        return <span className="admin-badge admin-badge-success">مجانية</span>;
    }
    if (course.is_on_sale) {
        return (
            <div className="course-price">
                <strong>{formatPrice(course.sale_price, course.currency)}</strong>
                <del>{formatPrice(course.price, course.currency)}</del>
                <span className="admin-badge admin-badge-danger">-{course.discount_percent}%</span>
            </div>
        );
    }
    return <strong>{formatPrice(course.price, course.currency)}</strong>;
}

export default function Index({ courses, filters, categories, instructors, statuses, levels, stats, flash }) {
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const applyFilters = (next) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, query, ...next }).filter(
                ([key, value]) => value !== '' && value != null && !(key === 'sort' && value === 'latest'),
            ),
        );
        router.get(route('admin.courses.index'), params, {
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

    const hasFilters = FILTER_KEYS.some((key) => filters[key]);
    const post = (name, course) => router.post(route(name, course.id), {}, { preserveScroll: true });
    const rows = courses.data || [];

    const select = (key, placeholder, options) => (
        <select
            className="form-select w-auto"
            value={filters[key]}
            onChange={(e) => applyFilters({ [key]: e.target.value })}
        >
            <option value="">{placeholder}</option>
            {options}
        </select>
    );

    return (
        <AdminUiPage
            title="الدورات"
            subtitle="أنشئ الدورات وأدِر محتواها وأسعارها ونشرها"
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            chips={[
                { label: 'الإجمالي', value: stats.total, icon: 'ri-book-open-line' },
                { label: 'منشورة', value: stats.published, icon: 'ri-checkbox-circle-line' },
                { label: 'مسودات', value: stats.draft, icon: 'ri-draft-line' },
                { label: 'مجانية', value: stats.free, icon: 'ri-gift-line' },
            ]}
            actions={
                <>
                    {can('admin.courses.restore') && (
                        <Link href={route('admin.courses.trash')} className="admin-btn admin-btn-ghost">
                            <i className="ri-delete-bin-6-line" />
                            سلة المحذوفات
                            {stats.trashed > 0 && (
                                <span className="admin-badge admin-badge-warning ms-1">{stats.trashed}</span>
                            )}
                        </Link>
                    )}
                    {can('admin.courses.create') && (
                        <Link href={route('admin.courses.create')} className="admin-btn admin-btn-primary">
                            <i className="ri-add-line" />
                            دورة جديدة
                        </Link>
                    )}
                </>
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={
                    itemToDelete
                        ? `سيتم نقل الدورة «${itemToDelete.title}» إلى سلة المحذوفات، ويمكنك استرجاعها لاحقاً.`
                        : ''
                }
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) {
                        return;
                    }
                    setDeleting(true);
                    router.delete(route('admin.courses.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="قائمة الدورات" icon="ri-book-open-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالعنوان أو المسار أو الوصف..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        {select(
                            'category',
                            'كل التصنيفات',
                            categories.map((root) => (
                                <optgroup key={root.id} label={root.name}>
                                    <option value={root.id}>{root.name} (الكل)</option>
                                    {root.children.map((child) => (
                                        <option key={child.id} value={child.id}>
                                            {child.name}
                                        </option>
                                    ))}
                                </optgroup>
                            )),
                        )}
                        {select('instructor', 'كل المدرّسين', [
                            <option key="none" value="none">
                                بدون مدرّس
                            </option>,
                            ...instructors.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            )),
                        ])}
                        {select(
                            'status',
                            'كل الحالات',
                            statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            )),
                        )}
                        {select(
                            'level',
                            'كل المستويات',
                            levels.map((l) => (
                                <option key={l.value} value={l.value}>
                                    {l.label}
                                </option>
                            )),
                        )}
                        {select('pricing', 'كل الأسعار', [
                            <option key="free" value="free">
                                مجانية
                            </option>,
                            <option key="paid" value="paid">
                                مدفوعة
                            </option>,
                            <option key="sale" value="sale">
                                عليها خصم
                            </option>,
                        ])}
                        {select('featured', 'مميزة وغير مميزة', [
                            <option key="1" value="1">
                                المميزة فقط
                            </option>,
                            <option key="0" value="0">
                                غير المميزة
                            </option>,
                        ])}
                        <select
                            className="form-select w-auto"
                            value={filters.sort}
                            onChange={(e) => applyFilters({ sort: e.target.value })}
                            title="الترتيب"
                        >
                            <option value="latest">الأحدث أولاً</option>
                            <option value="oldest">الأقدم أولاً</option>
                            <option value="title">حسب العنوان</option>
                            <option value="price_asc">السعر: من الأقل</option>
                            <option value="price_desc">السعر: من الأعلى</option>
                        </select>
                        {hasFilters && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                onClick={() => {
                                    setQuery('');
                                    router.get(route('admin.courses.index'), {}, { preserveScroll: true });
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
                                <th>الدورة</th>
                                <th>المدرّس</th>
                                <th>السعر</th>
                                <th>المستوى</th>
                                <th>المحتوى</th>
                                <th>الحالة</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((course) => {
                                const canUpdate = course.can?.update ?? can('admin.courses.edit');
                                const canDelete = course.can?.delete ?? can('admin.courses.delete');
                                const isPublished = course.status === 'published';

                                return (
                                    <tr key={course.id}>
                                        <td>
                                            <div className="d-flex align-items-center gap-3">
                                                <span className="course-thumb">
                                                    {course.thumbnail_url ? (
                                                        <img src={course.thumbnail_url} alt="" />
                                                    ) : (
                                                        <i className="ri-image-line" />
                                                    )}
                                                </span>
                                                <div className="min-w-0">
                                                    <strong className="d-block course-title">
                                                        {course.title}
                                                        {course.is_featured && (
                                                            <i
                                                                className="ri-star-fill text-warning ms-1"
                                                                title="مميزة"
                                                            />
                                                        )}
                                                    </strong>
                                                    <small className="text-muted">
                                                        <i className="ri-folder-3-line" />{' '}
                                                        {course.category?.name || '—'}
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {course.instructor?.name || <span className="text-muted">—</span>}
                                        </td>
                                        <td>
                                            <PriceCell course={course} />
                                        </td>
                                        <td>{course.level_label}</td>
                                        <td>
                                            <div className="small">
                                                <div>
                                                    <i className="ri-play-list-2-line text-muted" />{' '}
                                                    {course.lessons_count} درس
                                                </div>
                                                <div className="text-muted">
                                                    <i className="ri-time-line" />{' '}
                                                    {formatDuration(course.duration_seconds)}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span className={`admin-badge ${course.status_badge}`}>
                                                {course.status_label}
                                            </span>
                                            {course.hidden_reason && (
                                                <div
                                                    className="small fw-semibold mt-1"
                                                    style={{ color: '#b45309', maxWidth: 180 }}
                                                >
                                                    <i className="ri-eye-off-line" /> {course.hidden_reason}
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            <div className="admin-action-group">
                                                {canUpdate && (
                                                    <>
                                                        <Link
                                                            href={route('admin.courses.edit', course.id)}
                                                            className="admin-action-btn admin-action-edit"
                                                            title="تعديل"
                                                        >
                                                            <i className="ri-edit-line" />
                                                        </Link>
                                                        <Link
                                                            href={route(
                                                                'admin.courses.curriculum',
                                                                course.id,
                                                            )}
                                                            className="admin-action-btn admin-action-add"
                                                            title="المنهج (الأقسام والدروس)"
                                                        >
                                                            <i className="ri-list-check-3" />
                                                        </Link>
                                                        <Link
                                                            href={route('admin.courses.landing.edit', course.id)}
                                                            className="admin-action-btn admin-action-add"
                                                            style={
                                                                course.landing_published && isPublished
                                                                    ? { background: '#059669', borderColor: '#059669', color: '#fff' }
                                                                    : undefined
                                                            }
                                                            title={
                                                                course.landing_published === null
                                                                    ? 'صفحة الهبوط — لم تُنشأ بعد'
                                                                    : course.landing_published && isPublished
                                                                      ? 'صفحة الهبوط — منشورة ويراها الزوار'
                                                                      : 'صفحة الهبوط — غير منشورة'
                                                            }
                                                        >
                                                            <i className="ri-megaphone-line" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className={`admin-action-btn ${isPublished ? 'admin-action-on' : 'admin-action-off'}`}
                                                            title={
                                                                isPublished
                                                                    ? 'منشورة — اضغط لإلغاء النشر'
                                                                    : 'غير منشورة — اضغط للنشر'
                                                            }
                                                            onClick={() =>
                                                                post('admin.courses.toggle-publish', course)
                                                            }
                                                        >
                                                            <i
                                                                className={
                                                                    isPublished
                                                                        ? 'ri-eye-line'
                                                                        : 'ri-eye-off-line'
                                                                }
                                                            />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className={`admin-action-btn ${course.is_featured ? 'admin-action-star-on' : 'admin-action-star-off'}`}
                                                            title={
                                                                course.is_featured
                                                                    ? 'مميزة — اضغط لإلغاء التمييز'
                                                                    : 'غير مميزة — اضغط للتمييز'
                                                            }
                                                            onClick={() =>
                                                                post('admin.courses.toggle-featured', course)
                                                            }
                                                        >
                                                            <i
                                                                className={
                                                                    course.is_featured
                                                                        ? 'ri-star-fill'
                                                                        : 'ri-star-line'
                                                                }
                                                            />
                                                        </button>
                                                    </>
                                                )}
                                                {can('admin.courses.create') && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-view"
                                                        title="نسخ الدورة مع منهجها"
                                                        onClick={() =>
                                                            post('admin.courses.duplicate', course)
                                                        }
                                                    >
                                                        <i className="ri-file-copy-line" />
                                                    </button>
                                                )}
                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn admin-action-delete"
                                                        title="حذف"
                                                        onClick={() => setItemToDelete(course)}
                                                    >
                                                        <i className="ri-delete-bin-line" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={7} icon="ri-book-open-line">
                                    {hasFilters
                                        ? 'لا توجد دورات تطابق البحث.'
                                        : 'لا توجد دورات بعد. ابدأ بإنشاء أول دورة.'}
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={courses} />
            </AdminPanel>
        </AdminUiPage>
    );
}
