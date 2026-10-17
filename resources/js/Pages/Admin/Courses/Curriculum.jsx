import AdminUiPage from '@/Components/Admin/AdminUiPage';
import { formatDuration } from '@/Components/Admin/courseFormat';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import LessonModal from '@/Components/Admin/LessonModal';
import SectionModal from '@/Components/Admin/SectionModal';
import useSortable from '@/hooks/useSortable';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Curriculum({
    course,
    sections: initialSections,
    lessonTypes,
    videoSources,
    maxVideoUploadMb,
    flash,
}) {
    const [sections, setSections] = useState(initialSections);
    const [syncedSections, setSyncedSections] = useState(initialSections);
    if (initialSections !== syncedSections) {
        setSyncedSections(initialSections);
        setSections(initialSections);
    }

    // Accordion: which sections are open. All start folded.
    const [openSections, setOpenSections] = useState({});
    const [initialSectionIds] = useState(() => new Set(initialSections.map((section) => section.id)));
    const toggleSection = (id) =>
        setOpenSections((current) => ({
            ...current,
            [id]: !(current[id] ?? !initialSectionIds.has(id)),
        }));
    const setAllSections = (open) =>
        setOpenSections(Object.fromEntries(sections.map((section) => [section.id, open])));
    const [newSectionTitle, setNewSectionTitle] = useState('');
    const [sectionModal, setSectionModal] = useState(null); // section being edited
    const [lessonModal, setLessonModal] = useState(null); // { section, lesson? }
    const [pendingDelete, setPendingDelete] = useState(null); // { type, item }
    const [processing, setProcessing] = useState(false);

    const lessonsTotal = sections.reduce((sum, s) => sum + s.lessons.length, 0);
    const durationTotal = sections.reduce(
        (sum, s) =>
            sum + s.lessons.reduce((inner, l) => inner + (l.is_published ? l.duration_seconds : 0), 0),
        0,
    );

    const sortable = useSortable({
        onReorder: (group, ids) => {
            const original = sections;
            const byIds = (list) => ids.map((id) => list.find((item) => item.id === id));

            if (group === 'sections') {
                setSections(byIds(sections));
                router.post(
                    route('admin.courses.sections.reorder', course.id),
                    { ids },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onError: () => setSections(original),
                    },
                );
                return;
            }

            const sectionId = Number(group.replace('section-', ''));
            setSections(sections.map((s) => (s.id === sectionId ? { ...s, lessons: byIds(s.lessons) } : s)));
            router.post(
                route('admin.courses.lessons.reorder', [course.id, sectionId]),
                { ids },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onError: () => setSections(original),
                },
            );
        },
    });

    const addSection = (e) => {
        e.preventDefault();
        if (!newSectionTitle.trim()) {
            return;
        }
        router.post(
            route('admin.courses.sections.store', course.id),
            { title: newSectionTitle },
            {
                preserveScroll: true,
                onSuccess: () => setNewSectionTitle(''),
            },
        );
    };

    const confirmDelete = () => {
        if (!pendingDelete || processing) {
            return;
        }
        setProcessing(true);
        const url =
            pendingDelete.type === 'section'
                ? route('admin.courses.sections.destroy', [course.id, pendingDelete.item.id])
                : route('admin.courses.lessons.destroy', [course.id, pendingDelete.item.id]);
        router.delete(url, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPendingDelete(null);
            },
        });
    };

    const togglePublished = (lesson) =>
        router.put(
            route('admin.courses.lessons.update', [course.id, lesson.id]),
            {
                title: lesson.title,
                type: lesson.type,
                video_source: lesson.video_source || '',
                video: lesson.video || '',
                content: lesson.content || '',
                attachment: lesson.attachment || '',
                duration_seconds: lesson.duration_seconds,
                is_preview: lesson.is_preview,
                is_published: !lesson.is_published,
            },
            { preserveScroll: true },
        );

    const sectionIds = sections.map((s) => s.id);

    return (
        <AdminUiPage
            title="منهج الدورة"
            subtitle={course.title}
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            chips={[
                { label: 'الأقسام', value: sections.length, icon: 'ri-stack-line' },
                { label: 'الدروس', value: lessonsTotal, icon: 'ri-play-list-2-line' },
                { label: 'المدة', value: formatDuration(durationTotal), icon: 'ri-time-line' },
                { label: 'الحالة', value: course.status_label, icon: 'ri-flag-line' },
            ]}
            actions={
                <>
                    <Link
                        href={route('admin.courses.edit', course.id)}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-edit-line" />
                        بيانات الدورة
                    </Link>
                    <Link href={route('admin.courses.index')} className="admin-btn admin-btn-ghost">
                        <i className="ri-arrow-right-line" />
                        كل الدورات
                    </Link>
                </>
            }
        >
            {sectionModal && (
                <SectionModal
                    courseId={course.id}
                    section={sectionModal}
                    onClose={() => setSectionModal(null)}
                />
            )}

            {lessonModal && (
                <LessonModal
                    courseId={course.id}
                    section={lessonModal.section}
                    lesson={lessonModal.lesson}
                    sections={sections}
                    lessonTypes={lessonTypes}
                    videoSources={videoSources}
                    maxVideoUploadMb={maxVideoUploadMb}
                    onClose={() => setLessonModal(null)}
                />
            )}

            <DeleteConfirmModal
                show={Boolean(pendingDelete)}
                message={
                    pendingDelete
                        ? pendingDelete.type === 'section'
                            ? `سيتم حذف القسم «${pendingDelete.item.title}» وجميع دروسه (${pendingDelete.item.lessons.length}) نهائياً.`
                            : `سيتم حذف الدرس «${pendingDelete.item.title}» نهائياً مع ملفاته.`
                        : ''
                }
                processing={processing}
                onConfirm={confirmDelete}
                onCancel={() => !processing && setPendingDelete(null)}
            />

            {sections.length > 0 && (
                <div className="acc-toolbar">
                    <p className="text-muted small mb-0">
                        <i className="ri-draggable" /> اسحب الأقسام أو الدروس من المقبض لإعادة ترتيبها.
                    </p>
                    {sections.length > 1 && (
                        <div className="acc-toolbar-actions">
                            <button
                                type="button"
                                className="acc-toolbar-btn"
                                onClick={() => setAllSections(true)}
                            >
                                <i className="ri-expand-up-down-line" /> توسيع الكل
                            </button>
                            <button
                                type="button"
                                className="acc-toolbar-btn"
                                onClick={() => setAllSections(false)}
                            >
                                <i className="ri-contract-up-down-line" /> طي الكل
                            </button>
                        </div>
                    )}
                </div>
            )}

            <div className="curriculum" {...sortable.containerProps}>
                {sections.map((section, index) => {
                    const { className: sectionDragClass, ...sectionDrag } = sortable.itemProps(
                        section.id,
                        'sections',
                        sectionIds,
                    );
                    const lessonIds = section.lessons.map((l) => l.id);
                    // While a section is being dragged every section folds, so they are easy to move.
                    // A section added after the page loaded starts open, ready for its first lesson.
                    const isOpen =
                        (openSections[section.id] ?? !initialSectionIds.has(section.id)) &&
                        sortable.draggingGroup !== 'sections';
                    const previews = section.lessons.filter((l) => l.is_preview).length;
                    const hidden = section.lessons.filter((l) => !l.is_published).length;
                    const sectionDuration = section.lessons.reduce(
                        (sum, l) => sum + (l.is_published ? l.duration_seconds : 0),
                        0,
                    );

                    return (
                        <section
                            key={section.id}
                            className={`curriculum-section acc-section${isOpen ? ' is-open' : ''} ${sectionDragClass}`}
                            {...sectionDrag}
                        >
                            <header
                                className="curriculum-section__head acc-head"
                                onClick={(e) => {
                                    // Buttons and the drag handle keep their own job.
                                    if (!e.target.closest('button, .course-cat-handle')) {
                                        toggleSection(section.id);
                                    }
                                }}
                            >
                                <span
                                    className="course-cat-handle"
                                    title="اسحب لإعادة ترتيب القسم"
                                    {...sortable.handleProps(section.id, 'sections')}
                                >
                                    <i className="ri-draggable" />
                                </span>

                                <span className="acc-num en-text">{index + 1}</span>

                                <button
                                    type="button"
                                    className="acc-title"
                                    onClick={() => toggleSection(section.id)}
                                    aria-expanded={isOpen}
                                    aria-controls={`section-body-${section.id}`}
                                >
                                    <span className="acc-eyebrow">القسم {index + 1}</span>
                                    <strong>{section.title}</strong>
                                    <span className="acc-meta">
                                        <span>
                                            <i className="ri-play-list-2-line" /> {section.lessons.length} درس
                                        </span>
                                        <span>
                                            <i className="ri-time-line" /> {formatDuration(sectionDuration)}
                                        </span>
                                        {previews > 0 && (
                                            <span className="is-preview">
                                                <i className="ri-eye-line" /> {previews} معاينة
                                            </span>
                                        )}
                                        {hidden > 0 && (
                                            <span className="is-hidden">
                                                <i className="ri-eye-off-line" /> {hidden} مخفي
                                            </span>
                                        )}
                                    </span>
                                </button>

                                <div className="admin-action-group">
                                    <button
                                        type="button"
                                        className="admin-action-btn admin-action-add"
                                        title="إضافة درس"
                                        onClick={() => setLessonModal({ section })}
                                    >
                                        <i className="ri-add-line" />
                                    </button>
                                    <button
                                        type="button"
                                        className="admin-action-btn admin-action-edit"
                                        title="تعديل العنوان والوصف"
                                        onClick={() => setSectionModal(section)}
                                    >
                                        <i className="ri-edit-line" />
                                    </button>
                                    <button
                                        type="button"
                                        className="admin-action-btn admin-action-delete"
                                        title="حذف القسم"
                                        onClick={() => setPendingDelete({ type: 'section', item: section })}
                                    >
                                        <i className="ri-delete-bin-line" />
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    className="acc-chevron"
                                    onClick={() => toggleSection(section.id)}
                                    aria-label={isOpen ? 'طي القسم' : 'توسيع القسم'}
                                >
                                    <i className="ri-arrow-down-s-line" />
                                </button>
                            </header>

                            <div className="acc-body" id={`section-body-${section.id}`} aria-hidden={!isOpen}>
                                <div className="acc-body-inner">
                                    {section.description && (
                                        <div
                                            className="curriculum-section__desc"
                                            // Admin-authored HTML from the rich-text editor.
                                            dangerouslySetInnerHTML={{ __html: section.description }}
                                        />
                                    )}

                                    {
                                        <div className="curriculum-lessons">
                                            {section.lessons.map((lesson) => {
                                                const { className: lessonDragClass, ...lessonDrag } =
                                                    sortable.itemProps(
                                                        lesson.id,
                                                        `section-${section.id}`,
                                                        lessonIds,
                                                    );
                                                return (
                                                    <div
                                                        key={lesson.id}
                                                        className={`curriculum-lesson${lesson.is_published ? '' : ' is-hidden'} ${lessonDragClass}`}
                                                        {...lessonDrag}
                                                    >
                                                        <span
                                                            className="course-cat-handle"
                                                            title="اسحب لإعادة ترتيب الدرس"
                                                            {...sortable.handleProps(
                                                                lesson.id,
                                                                `section-${section.id}`,
                                                            )}
                                                        >
                                                            <i className="ri-draggable" />
                                                        </span>
                                                        <span
                                                            className={`lesson-type-icon is-${lesson.type}`}
                                                            title={lesson.type_label}
                                                        >
                                                            <i className={lesson.type_icon} />
                                                        </span>
                                                        <div className="flex-grow-1 min-w-0">
                                                            <span className="d-block text-truncate">
                                                                {lesson.title}
                                                            </span>
                                                            <small className="text-muted">
                                                                {lesson.type_label}
                                                                {lesson.attachments?.length > 0 && (
                                                                    <>
                                                                        {' · '}
                                                                        <i className="ri-attachment-2" />{' '}
                                                                        {lesson.attachments.length} مرفق
                                                                    </>
                                                                )}
                                                            </small>
                                                        </div>
                                                        {lesson.is_preview && (
                                                            <span className="admin-badge admin-badge-info">
                                                                معاينة مجانية
                                                            </span>
                                                        )}
                                                        {!lesson.is_published && (
                                                            <span className="admin-badge admin-badge-muted">
                                                                مخفي
                                                            </span>
                                                        )}
                                                        <span className="text-muted small text-nowrap">
                                                            <i className="ri-time-line" />{' '}
                                                            {formatDuration(lesson.duration_seconds)}
                                                        </span>
                                                        <div className="admin-action-group">
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-edit"
                                                                title="تعديل"
                                                                onClick={() =>
                                                                    setLessonModal({ section, lesson })
                                                                }
                                                            >
                                                                <i className="ri-edit-line" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className={`admin-action-btn ${lesson.is_published ? 'admin-action-on' : 'admin-action-off'}`}
                                                                title={
                                                                    lesson.is_published
                                                                        ? 'منشور — اضغط للإخفاء'
                                                                        : 'مخفي — اضغط للنشر'
                                                                }
                                                                onClick={() => togglePublished(lesson)}
                                                            >
                                                                <i
                                                                    className={
                                                                        lesson.is_published
                                                                            ? 'ri-eye-line'
                                                                            : 'ri-eye-off-line'
                                                                    }
                                                                />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="admin-action-btn admin-action-delete"
                                                                title="حذف"
                                                                onClick={() =>
                                                                    setPendingDelete({
                                                                        type: 'lesson',
                                                                        item: lesson,
                                                                    })
                                                                }
                                                            >
                                                                <i className="ri-delete-bin-line" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                            {section.lessons.length === 0 && (
                                                <button
                                                    type="button"
                                                    className="curriculum-empty"
                                                    onClick={() => setLessonModal({ section })}
                                                >
                                                    <i className="ri-add-circle-line" /> لا توجد دروس بعد —
                                                    أضف أول درس
                                                </button>
                                            )}
                                        </div>
                                    }
                                </div>
                            </div>
                        </section>
                    );
                })}

                {sections.length === 0 && (
                    <div className="curriculum-placeholder">
                        <i className="ri-stack-line" />
                        <strong>ابدأ ببناء المنهج</strong>
                        <span>
                            قسّم الدورة إلى أقسام (مثل: المقدمة، الأساسيات، مشروع تطبيقي) ثم أضف الدروس داخل
                            كل قسم.
                        </span>
                    </div>
                )}
            </div>

            <form className="curriculum-add-section" onSubmit={addSection}>
                <i className="ri-folder-add-line" />
                <input
                    className="form-control"
                    placeholder="عنوان قسم جديد، مثال: المقدمة"
                    value={newSectionTitle}
                    onChange={(e) => setNewSectionTitle(e.target.value)}
                />
                <button
                    type="submit"
                    className="admin-btn admin-btn-primary"
                    disabled={!newSectionTitle.trim()}
                >
                    <i className="ri-add-line" />
                    إضافة قسم
                </button>
            </form>
        </AdminUiPage>
    );
}
