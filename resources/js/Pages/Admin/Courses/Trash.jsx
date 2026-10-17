import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Trash({ courses, filters, flash }) {
    const [query, setQuery] = useState(filters.query || '');
    // { type: 'restore' | 'force', course }
    const [pending, setPending] = useState(null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (query === (filters.query || '')) {
            return undefined;
        }
        const timer = setTimeout(() => {
            router.get(route('admin.courses.trash'), query ? { query } : {}, {
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
            router.post(route('admin.courses.restore', pending.course.id), {}, options);
        } else {
            router.delete(route('admin.courses.force-delete', pending.course.id), options);
        }
    };

    const rows = courses.data || [];

    return (
        <AdminUiPage
            title="سلة محذوفات الدورات"
            subtitle="استرجع الدورات المحذوفة أو احذفها نهائياً مع منهجها"
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            actions={
                <Link href={route('admin.courses.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للدورات
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
                            ? `ستعود الدورة «${pending.course.title}» إلى قائمة الدورات.`
                            : `سيتم حذف «${pending.course.title}» نهائياً مع أقسامها ودروسها وملفاتها، ولا يمكن التراجع.`
                        : ''
                }
                processing={processing}
                onConfirm={confirm}
                onCancel={() => !processing && setPending(null)}
            />

            <AdminPanel title="الدورات المحذوفة" icon="ri-delete-bin-6-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالعنوان..."
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
                                <th>الدورة</th>
                                <th>التصنيف</th>
                                <th>المدرّس</th>
                                <th>الدروس</th>
                                <th>تاريخ الحذف</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((course) => (
                                <tr key={course.id}>
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <span className="course-thumb">
                                                {course.thumbnail_url ? (
                                                    <img src={course.thumbnail_url} alt="" />
                                                ) : (
                                                    <i className="ri-image-line" />
                                                )}
                                            </span>
                                            <strong>{course.title}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        {course.category?.name || '—'}
                                        {course.category_trashed && (
                                            <span className="admin-badge admin-badge-warning ms-2">
                                                محذوف
                                            </span>
                                        )}
                                    </td>
                                    <td>{course.instructor?.name || '—'}</td>
                                    <td>{course.lessons_count}</td>
                                    <td>{course.deleted_at}</td>
                                    <td>
                                        <div className="admin-action-group">
                                            {/* Row flags carry ownership (edit-any / delete-any). */}
                                            {course.can?.update && (
                                            <button
                                                type="button"
                                                className="admin-action-btn admin-action-restore"
                                                title={
                                                    course.category_trashed
                                                        ? 'استرجع التصنيف أولاً'
                                                        : 'استرجاع'
                                                }
                                                disabled={course.category_trashed}
                                                onClick={() => setPending({ type: 'restore', course })}
                                            >
                                                <i className="ri-arrow-go-back-line" />
                                            </button>
                                            )}
                                            {course.can?.delete && (
                                            <button
                                                type="button"
                                                className="admin-action-btn admin-action-delete"
                                                title="حذف نهائي"
                                                onClick={() => setPending({ type: 'force', course })}
                                            >
                                                <i className="ri-delete-bin-line" />
                                            </button>
                                            )}
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

                <Pagination paginator={courses} />
            </AdminPanel>
        </AdminUiPage>
    );
}
