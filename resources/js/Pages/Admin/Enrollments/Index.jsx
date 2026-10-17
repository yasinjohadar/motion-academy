import Portal from '@/Components/Admin/Portal';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import useCan from '@/hooks/useCan';
import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const TABS = [
    { value: 'pending', label: 'بانتظار الموافقة', icon: 'ri-time-line' },
    { value: 'approved', label: 'المنضمون', icon: 'ri-checkbox-circle-line' },
    { value: 'rejected', label: 'المرفوضة', icon: 'ri-close-circle-line' },
    { value: 'all', label: 'الكل', icon: 'ri-list-check' },
];

const STATUS_BADGE = {
    pending: 'admin-badge-warning',
    approved: 'admin-badge-success',
    rejected: 'admin-badge-muted',
};

/** Student picker: type a name or email, pick from the matches. */
function StudentPicker({ courseId, value, onChange, error }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const controller = useRef(null);

    useEffect(() => {
        if (value || query.trim().length < 2) {
            return undefined;
        }
        const timer = setTimeout(async () => {
            controller.current?.abort();
            controller.current = new AbortController();
            setLoading(true);
            try {
                const url = route('admin.enrollments.students', {
                    q: query.trim(),
                    course: courseId || undefined,
                });
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.current.signal,
                });
                const data = await response.json();
                setResults(data.students || []);
                setOpen(true);
            } catch (e) {
                if (e.name !== 'AbortError') setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [query, courseId, value]);

    if (value) {
        return (
            <div className="enr-picked">
                <span className="enr-avatar">{value.name.charAt(0)}</span>
                <div className="min-w-0 flex-grow-1">
                    <strong className="d-block">{value.name}</strong>
                    <small className="text-muted" dir="ltr">
                        {value.email}
                    </small>
                </div>
                <button
                    type="button"
                    className="admin-action-btn admin-action-off"
                    title="تغيير"
                    onClick={() => onChange(null)}
                >
                    <i className="ri-close-line" />
                </button>
            </div>
        );
    }

    return (
        <div className="enr-picker">
            <div className="search-input-wrap">
                <i className={loading ? 'ri-loader-4-line' : 'ri-search-line'} />
                <input
                    type="search"
                    className={`form-control${error ? ' is-invalid' : ''}`}
                    placeholder="ابحث باسم الطالب أو بريده (حرفان على الأقل)..."
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => results.length && setOpen(true)}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                />
            </div>
            {error && <div className="invalid-feedback d-block">{error}</div>}
            {open && (
                <ul className="enr-picker-list">
                    {results.length === 0 && <li className="enr-picker-empty">لا يوجد طلاب مطابقون.</li>}
                    {results.map((student) => {
                        const already = student.enrollment_status === 'approved';
                        return (
                            <li key={student.id}>
                                <button
                                    type="button"
                                    disabled={already}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => {
                                        onChange(student);
                                        setQuery('');
                                        setOpen(false);
                                    }}
                                >
                                    <span className="enr-avatar">{student.name.charAt(0)}</span>
                                    <span className="min-w-0 flex-grow-1 text-start">
                                        <strong className="d-block">{student.name}</strong>
                                        <small className="text-muted" dir="ltr">
                                            {student.email}
                                        </small>
                                    </span>
                                    {already && <span className="admin-badge admin-badge-success">منضم</span>}
                                    {student.enrollment_status === 'pending' && (
                                        <span className="admin-badge admin-badge-warning">لديه طلب</span>
                                    )}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

export default function Index({ enrollments, filters, counts, courses, flash }) {
    const { can } = useCan();
    const { errors: pageErrors } = usePage().props;
    const [query, setQuery] = useState(filters.query || '');
    const [showAdd, setShowAdd] = useState(false);
    const [student, setStudent] = useState(null);
    const [selected, setSelected] = useState([]);
    const [rejecting, setRejecting] = useState(null);
    const [reason, setReason] = useState('');
    const [toRemove, setToRemove] = useState(null);
    const [busy, setBusy] = useState(false);

    const addForm = useForm({ course_id: filters.course || '', user_id: '', note: '' });
    const addErrors = { ...pageErrors, ...addForm.errors };
    const rows = enrollments.data || [];
    const pendingIds = rows.filter((row) => row.status === 'pending').map((row) => row.id);
    const canDecide = can('admin.enrollments.edit');

    const applyFilters = (next) => {
        const params = Object.fromEntries(
            Object.entries({ ...filters, query, ...next }).filter(
                ([, value]) => value !== '' && value != null,
            ),
        );
        setSelected([]);
        router.get(route('admin.enrollments.index'), params, {
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

    const post = (name, id, data = {}) =>
        router.post(route(name, id), data, { preserveScroll: true, onSuccess: () => setSelected([]) });

    const submitAdd = (e) => {
        e.preventDefault();
        addForm.transform((data) => ({ ...data, user_id: student?.id || '' }));
        addForm.post(route('admin.enrollments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setStudent(null);
                addForm.reset('user_id', 'note');
            },
        });
    };

    const toggle = (id) =>
        setSelected((current) => (current.includes(id) ? current.filter((x) => x !== id) : [...current, id]));
    const allSelected = pendingIds.length > 0 && pendingIds.every((id) => selected.includes(id));

    return (
        <AdminUiPage
            title="الانضمام للدورات"
            subtitle="راجع طلبات الطلاب، أو أضف أي طالب لديه حساب إلى دورة مباشرة"
            eyebrowIcon="ri-user-follow-line"
            flash={flash}
            chips={[
                { label: 'بانتظار الموافقة', value: counts.pending, icon: 'ri-time-line' },
                { label: 'المنضمون', value: counts.approved, icon: 'ri-checkbox-circle-line' },
                { label: 'المرفوضة', value: counts.rejected, icon: 'ri-close-circle-line' },
            ]}
            actions={
                can('admin.enrollments.create') && (
                    <button
                        type="button"
                        className="admin-btn admin-btn-primary"
                        onClick={() => setShowAdd((open) => !open)}
                    >
                        <i className={showAdd ? 'ri-close-line' : 'ri-user-add-line'} />
                        {showAdd ? 'إغلاق' : 'إضافة طالب لدورة'}
                    </button>
                )
            }
        >
            {/* Reject dialog */}
            {rejecting && (
                <Portal>
                    <div
                        className="modal fade show d-block"
                        tabIndex="-1"
                        role="dialog"
                        style={{ background: 'rgba(15, 23, 42, 0.55)' }}
                    >
                        <div className="modal-dialog modal-dialog-centered">
                            <div className="modal-content">
                                <div className="modal-header">
                                    <h5 className="modal-title">رفض طلب الانضمام</h5>
                                    <button
                                        type="button"
                                        className="btn-close"
                                        onClick={() => setRejecting(null)}
                                        disabled={busy}
                                    />
                                </div>
                                <div className="modal-body">
                                    <p className="mb-2">
                                        طلب <strong>{rejecting.user?.name}</strong> للانضمام إلى «
                                        {rejecting.course?.title}».
                                    </p>
                                    <label className="form-label">سبب الرفض (يظهر للطالب — اختياري)</label>
                                    <textarea
                                        className="form-control"
                                        rows={3}
                                        maxLength={500}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder="مثال: اكتملت المقاعد لهذه الدفعة، سنفتح دفعة جديدة قريباً."
                                        autoFocus
                                    />
                                </div>
                                <div className="modal-footer">
                                    <button
                                        type="button"
                                        className="admin-btn admin-btn-ghost"
                                        onClick={() => setRejecting(null)}
                                        disabled={busy}
                                    >
                                        إلغاء
                                    </button>
                                    <button
                                        type="button"
                                        className="admin-btn admin-btn-danger"
                                        disabled={busy}
                                        onClick={() => {
                                            setBusy(true);
                                            router.post(
                                                route('admin.enrollments.reject', rejecting.id),
                                                { reason },
                                                {
                                                    preserveScroll: true,
                                                    onFinish: () => {
                                                        setBusy(false);
                                                        setRejecting(null);
                                                        setReason('');
                                                    },
                                                },
                                            );
                                        }}
                                    >
                                        <i className="ri-close-circle-line" /> رفض الطلب
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Portal>
            )}

            <DeleteConfirmModal
                show={Boolean(toRemove)}
                title="إزالة من الدورة"
                confirmLabel="إزالة"
                message={
                    toRemove ? `سيتم حذف سجل ${toRemove.user?.name} في «${toRemove.course?.title}».` : ''
                }
                processing={busy}
                onConfirm={() => {
                    setBusy(true);
                    router.delete(route('admin.enrollments.destroy', toRemove.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setBusy(false);
                            setToRemove(null);
                        },
                    });
                }}
                onCancel={() => !busy && setToRemove(null)}
            />

            {showAdd && (
                <AdminPanel title="إضافة طالب إلى دورة" icon="ri-user-add-line" className="mb-4">
                    <form onSubmit={submitAdd} className="row g-3 align-items-start">
                        <div className="col-lg-4">
                            <label className="form-label">الدورة *</label>
                            <select
                                className={`form-select${addErrors.course_id ? ' is-invalid' : ''}`}
                                value={addForm.data.course_id}
                                onChange={(e) => addForm.setData('course_id', e.target.value)}
                            >
                                <option value="">— اختر الدورة —</option>
                                {courses.map((course) => (
                                    <option key={course.id} value={course.id}>
                                        {course.title}
                                        {course.max_students ? ` (حد ${course.max_students})` : ''}
                                    </option>
                                ))}
                            </select>
                            {addErrors.course_id && (
                                <div className="invalid-feedback">{addErrors.course_id}</div>
                            )}
                        </div>
                        <div className="col-lg-5">
                            <label className="form-label">الطالب *</label>
                            <StudentPicker
                                courseId={addForm.data.course_id}
                                value={student}
                                onChange={setStudent}
                                error={addErrors.user_id}
                            />
                        </div>
                        <div className="col-lg-3">
                            <label className="form-label d-none d-lg-block">&nbsp;</label>
                            <button
                                type="submit"
                                className="admin-btn admin-btn-primary w-100"
                                disabled={addForm.processing || !student || !addForm.data.course_id}
                            >
                                <i className="ri-check-line" /> إضافة وتفعيل مباشرة
                            </button>
                        </div>
                        <div className="col-12">
                            <input
                                className="form-control"
                                placeholder="ملاحظة داخلية (اختياري)"
                                value={addForm.data.note}
                                onChange={(e) => addForm.setData('note', e.target.value)}
                            />
                        </div>
                    </form>
                </AdminPanel>
            )}

            <AdminPanel title="الطلبات والانضمامات" icon="ri-user-follow-line">
                <div className="enr-tabs">
                    {TABS.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            className={`enr-tab${filters.status === tab.value ? ' is-active' : ''}${tab.value === 'pending' && counts.pending > 0 ? ' has-pending' : ''}`}
                            onClick={() => applyFilters({ status: tab.value })}
                        >
                            <i className={tab.icon} /> {tab.label}
                            <span className="enr-tab-count">{counts[tab.value]}</span>
                        </button>
                    ))}
                </div>

                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث باسم الطالب أو بريده..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select w-auto"
                            value={filters.course}
                            onChange={(e) => applyFilters({ course: e.target.value })}
                        >
                            <option value="">كل الدورات</option>
                            {courses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.title}
                                </option>
                            ))}
                        </select>
                        {canDecide && selected.length > 0 && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-primary"
                                onClick={() =>
                                    router.post(
                                        route('admin.enrollments.bulk-approve'),
                                        { ids: selected },
                                        { preserveScroll: true, onSuccess: () => setSelected([]) },
                                    )
                                }
                            >
                                <i className="ri-check-double-line" /> قبول المحدد ({selected.length})
                            </button>
                        )}
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                {canDecide && (
                                    <th style={{ width: 40 }}>
                                        <input
                                            type="checkbox"
                                            className="form-check-input"
                                            title="تحديد كل الطلبات المعلّقة"
                                            disabled={pendingIds.length === 0}
                                            checked={allSelected}
                                            onChange={() => setSelected(allSelected ? [] : pendingIds)}
                                        />
                                    </th>
                                )}
                                <th>الطالب</th>
                                <th>الدورة</th>
                                <th>الرسالة / الملاحظة</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th>العمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={row.id}
                                    className={row.status === 'pending' ? 'enr-row-pending' : ''}
                                >
                                    {canDecide && (
                                        <td>
                                            {row.status === 'pending' && (
                                                <input
                                                    type="checkbox"
                                                    className="form-check-input"
                                                    checked={selected.includes(row.id)}
                                                    onChange={() => toggle(row.id)}
                                                />
                                            )}
                                        </td>
                                    )}
                                    <td>
                                        <div className="d-flex align-items-center gap-2">
                                            <span className="enr-avatar">
                                                {row.user?.name?.charAt(0) || '?'}
                                            </span>
                                            <div className="min-w-0">
                                                <strong className="d-block">{row.user?.name}</strong>
                                                <small className="text-muted" dir="ltr">
                                                    {row.user?.email}
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong className="d-block enr-course">{row.course?.title}</strong>
                                        <span
                                            className={`admin-badge ${row.source === 'admin' ? 'admin-badge-info' : 'admin-badge-muted'}`}
                                        >
                                            {row.source === 'admin' ? 'إضافة من الإدارة' : 'طلب من الطالب'}
                                        </span>
                                    </td>
                                    <td className="enr-message">
                                        {row.message && (
                                            <span>
                                                <i className="ri-chat-quote-line" /> {row.message}
                                            </span>
                                        )}
                                        {row.admin_note && (
                                            <small className="d-block text-muted">
                                                <i className="ri-sticky-note-line" /> {row.admin_note}
                                            </small>
                                        )}
                                        {!row.message && !row.admin_note && (
                                            <span className="text-muted">—</span>
                                        )}
                                    </td>
                                    <td>
                                        <span className={`admin-badge ${STATUS_BADGE[row.status]}`}>
                                            {row.status_label}
                                        </span>
                                        {row.decider && (
                                            <small className="d-block text-muted mt-1">
                                                بواسطة {row.decider}
                                            </small>
                                        )}
                                    </td>
                                    <td className="text-muted small">
                                        <div>{row.created_at}</div>
                                        {row.decided_at && <div>قرار: {row.decided_at}</div>}
                                    </td>
                                    <td>
                                        <div className="admin-action-group">
                                            {canDecide && row.status !== 'approved' && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-on"
                                                    title="قبول"
                                                    onClick={() => post('admin.enrollments.approve', row.id)}
                                                >
                                                    <i className="ri-check-line" />
                                                </button>
                                            )}
                                            {canDecide && row.status === 'pending' && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-off"
                                                    title="رفض"
                                                    onClick={() => {
                                                        setReason('');
                                                        setRejecting(row);
                                                    }}
                                                >
                                                    <i className="ri-close-line" />
                                                </button>
                                            )}
                                            {can('admin.enrollments.delete') && (
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-delete"
                                                    title={
                                                        row.status === 'approved'
                                                            ? 'إزالة من الدورة'
                                                            : 'حذف الطلب'
                                                    }
                                                    onClick={() => setToRemove(row)}
                                                >
                                                    <i className="ri-delete-bin-line" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={canDecide ? 7 : 6} icon="ri-inbox-line">
                                    {filters.status === 'pending'
                                        ? 'لا توجد طلبات بانتظار الموافقة.'
                                        : 'لا توجد سجلات مطابقة.'}
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={enrollments} />
            </AdminPanel>
        </AdminUiPage>
    );
}
