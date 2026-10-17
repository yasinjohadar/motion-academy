import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

/**
 * Add one student to a course, from the users list. Uses the same endpoint as
 * the enrollments page (admin.enrollments.store), so the rules are shared:
 * already-enrolled is refused, a pending or rejected request is approved, and
 * the student is notified.
 */
const STATUS = {
    approved: { label: 'منضم بالفعل', className: 'is-approved', icon: 'ri-checkbox-circle-line' },
    pending: { label: 'طلبه معلّق — سيُقبل', className: 'is-pending', icon: 'ri-hourglass-line' },
    rejected: { label: 'رُفض سابقاً — سيُقبل', className: 'is-rejected', icon: 'ri-refresh-line' },
};

export default function EnrollStudentModal({ user, onClose }) {
    const [data, setData] = useState({ status: 'loading', courses: [], error: '' });
    const [search, setSearch] = useState('');
    const [courseId, setCourseId] = useState(null);
    const [note, setNote] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!user) {
            return undefined;
        }

        // Mounted fresh per student (key in Users/Index), so state starts clean.
        let cancelled = false;

        window.axios
            .get(route('admin.enrollments.courses-for', user.id))
            .then(({ data: payload }) => {
                if (!cancelled) {
                    setData({ status: 'ready', courses: payload.courses, error: '' });
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setData({ status: 'error', courses: [], error: 'تعذّر تحميل الدورات.' });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [user]);

    useEffect(() => {
        if (!user) {
            return undefined;
        }

        const onKeyDown = (event) => event.key === 'Escape' && !submitting && onClose();
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [user, onClose, submitting]);

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        return term
            ? data.courses.filter(
                  (course) =>
                      course.title.toLowerCase().includes(term) ||
                      (course.category || '').toLowerCase().includes(term),
              )
            : data.courses;
    }, [data.courses, search]);

    if (!user) {
        return null;
    }

    const submit = (e) => {
        e.preventDefault();
        if (!courseId) {
            return;
        }

        setSubmitting(true);
        setError('');
        router.post(
            route('admin.enrollments.store'),
            { user_id: user.id, course_id: courseId, note: note || null },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => setError(Object.values(errors)[0] || 'تعذّرت الإضافة.'),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    // Rendered into <body>: a fixed overlay inside an animated (transformed)
    // page container would be positioned against that container, not the screen.
    return createPortal(
        <div
            className="admin-delete-modal enr-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="enr-modal-title"
        >
            <button
                type="button"
                className="admin-delete-modal-backdrop"
                aria-label="إغلاق"
                onClick={() => !submitting && onClose()}
            />

            <div className="admin-delete-modal-dialog">
                <form className="admin-delete-modal-content enr-modal-content" onSubmit={submit}>
                    <div className="enr-modal-head">
                        <span className="enr-modal-icon" aria-hidden="true">
                            <i className="ri-graduation-cap-line" />
                        </span>
                        <div>
                            <h4 id="enr-modal-title" className="enr-modal-title">
                                إضافة إلى دورة
                            </h4>
                            <p className="enr-modal-sub">
                                {user.name_ar || user.name_en} <span dir="ltr">· {user.email}</span>
                            </p>
                        </div>
                    </div>

                    <div className="enr-modal-search">
                        <i className="ri-search-line" />
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="ابحث باسم الدورة أو التصنيف…"
                            aria-label="بحث في الدورات"
                        />
                    </div>

                    <div className="enr-modal-list" role="radiogroup" aria-label="الدورات">
                        {data.status === 'loading' && (
                            <div className="enr-modal-empty">
                                <span className="spinner-border spinner-border-sm" /> جارٍ تحميل الدورات…
                            </div>
                        )}
                        {data.status === 'error' && <div className="enr-modal-empty">{data.error}</div>}
                        {data.status === 'ready' && visible.length === 0 && (
                            <div className="enr-modal-empty">لا توجد دورات مطابقة.</div>
                        )}
                        {visible.map((course) => {
                            const status = STATUS[course.enrollment_status];
                            // Full courses are refused by EnrollmentService, so they cannot be picked.
                            const disabled = course.enrollment_status === 'approved' || course.full;

                            return (
                                <label
                                    key={course.id}
                                    className={`enr-option${courseId === course.id ? ' is-selected' : ''}${disabled ? ' is-disabled' : ''}`}
                                >
                                    <input
                                        type="radio"
                                        name="course_id"
                                        value={course.id}
                                        checked={courseId === course.id}
                                        disabled={disabled}
                                        onChange={() => setCourseId(course.id)}
                                    />
                                    <span className="enr-option-check" aria-hidden="true">
                                        <i className="ri-check-line" />
                                    </span>
                                    <span className="enr-option-body">
                                        <strong>{course.title}</strong>
                                        <span className="enr-option-meta">
                                            {course.category && <span>{course.category}</span>}
                                            {!course.published && (
                                                <span className="enr-tag">{course.status_label}</span>
                                            )}
                                            {course.is_free && (
                                                <span className="enr-tag is-free">مجانية</span>
                                            )}
                                            {course.full && (
                                                <span className="enr-tag is-full">لا مقاعد متاحة</span>
                                            )}
                                        </span>
                                    </span>
                                    {status && (
                                        <span className={`enr-status ${status.className}`}>
                                            <i className={status.icon} /> {status.label}
                                        </span>
                                    )}
                                </label>
                            );
                        })}
                    </div>

                    <textarea
                        className="form-control enr-modal-note"
                        rows={2}
                        maxLength={1000}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="ملاحظة للطالب (اختياري) — تصله مع إشعار الانضمام"
                    />

                    {error && <div className="admin-alert admin-alert-error mb-0">{error}</div>}

                    <div className="admin-delete-modal-actions">
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost"
                            disabled={submitting}
                            onClick={onClose}
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={!courseId || submitting}
                        >
                            {submitting ? (
                                <span className="spinner-border spinner-border-sm" />
                            ) : (
                                <i className="ri-user-add-line" />
                            )}
                            إضافة إلى الدورة
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}
