import LessonAttachmentsField, {
    attachmentPayload,
    attachmentRows,
} from '@/Components/Admin/LessonAttachmentsField';
import Portal from '@/Components/Admin/Portal';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import VideoSourceField from '@/Components/Admin/VideoSourceField';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Reads a local video file's length so the duration fills itself in. */
function readVideoDuration(file, onDuration) {
    const url = URL.createObjectURL(file);
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
        URL.revokeObjectURL(url);
        if (Number.isFinite(video.duration)) {
            onDuration(Math.round(video.duration));
        }
    };
    video.onerror = () => URL.revokeObjectURL(url);
    video.src = url;
}

/**
 * Create/edit a lesson. `lesson` set → edit; otherwise a new lesson in `section`.
 */
export default function LessonModal({
    courseId,
    section,
    lesson = null,
    sections = [],
    lessonTypes = [],
    videoSources = [],
    maxVideoUploadMb,
    onClose,
}) {
    const { errors: pageErrors } = usePage().props;
    const isEdit = Boolean(lesson);
    const initialSeconds = lesson?.duration_seconds ?? 0;

    const { data, setData, post, processing, errors, progress, transform, clearErrors } = useForm({
        title: lesson?.title || '',
        type: lesson?.type || 'video',
        video_source: lesson?.video_source || 'youtube',
        video: lesson?.video || '',
        video_file: null,
        content: lesson?.content || '',
        attachment: lesson?.attachment || '',
        attachment_file: null,
        remove_attachment: false,
        minutes: Math.floor(initialSeconds / 60),
        seconds: initialSeconds % 60,
        is_preview: Boolean(lesson?.is_preview),
        is_published: lesson ? Boolean(lesson.is_published) : true,
        course_section_id: lesson?.course_section_id ?? section?.id,
        ...(isEdit ? { _method: 'put' } : {}),
    });

    const fieldErrors = { ...pageErrors, ...errors };
    const [attachments, setAttachments] = useState(() => attachmentRows(lesson?.attachments));

    useEffect(() => {
        const onKeyDown = (event) => event.key === 'Escape' && !processing && onClose();
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [processing, onClose]);

    const setDuration = (total) =>
        setData((current) => ({ ...current, minutes: Math.floor(total / 60), seconds: total % 60 }));

    const submit = (e) => {
        e.preventDefault();
        transform(({ minutes, seconds, ...payload }) => ({
            ...payload,
            duration_seconds: (Number(minutes) || 0) * 60 + (Number(seconds) || 0),
            video_source: payload.type === 'video' ? payload.video_source : '',
            attachments: attachmentPayload(attachments),
            // Tells the server an empty list means "remove them all".
            attachments_present: true,
        }));
        post(
            isEdit
                ? route('admin.courses.lessons.update', [courseId, lesson.id])
                : route('admin.courses.lessons.store', [courseId, section.id]),
            {
                forceFormData: true,
                // attachments[0][title], not attachments[][title] — PHP would split the latter.
                queryStringArrayFormat: 'indices',
                preserveScroll: true,
                onSuccess: () => onClose(),
            },
        );
    };

    const isVideo = data.type === 'video';
    const existingAttachment = lesson?.attachment_name && !data.remove_attachment;

    return (
        <Portal>
            <div
                className="modal fade show d-block"
                tabIndex="-1"
                role="dialog"
                style={{ background: 'rgba(15, 23, 42, 0.55)' }}
            >
                <div className="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                    <form className="modal-content" onSubmit={submit}>
                        <div className="modal-header">
                            <h5 className="modal-title">
                                <i className={isEdit ? 'ri-edit-line' : 'ri-add-circle-line'} />{' '}
                                {isEdit ? 'تعديل الدرس' : `درس جديد في «${section?.title}»`}
                            </h5>
                            <button
                                type="button"
                                className="btn-close"
                                onClick={onClose}
                                disabled={processing}
                            />
                        </div>

                        <div className="modal-body">
                            <div className="row g-3">
                                <div className="col-lg-8">
                                    <label className="form-label">عنوان الدرس *</label>
                                    <input
                                        type="text"
                                        autoFocus
                                        className={`form-control${fieldErrors.title ? ' is-invalid' : ''}`}
                                        value={data.title}
                                        onChange={(e) => setData('title', e.target.value)}
                                    />
                                    {fieldErrors.title && (
                                        <div className="invalid-feedback">{fieldErrors.title}</div>
                                    )}
                                </div>
                                <div className="col-lg-4">
                                    <label className="form-label">القسم</label>
                                    <select
                                        className={`form-select${fieldErrors.course_section_id ? ' is-invalid' : ''}`}
                                        value={data.course_section_id}
                                        disabled={!isEdit}
                                        onChange={(e) => setData('course_section_id', Number(e.target.value))}
                                    >
                                        {sections.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.title}
                                            </option>
                                        ))}
                                    </select>
                                    {fieldErrors.course_section_id && (
                                        <div className="invalid-feedback">
                                            {fieldErrors.course_section_id}
                                        </div>
                                    )}
                                </div>

                                <div className="col-12">
                                    <label className="form-label">نوع الدرس</label>
                                    <div className="lesson-type-picker">
                                        {lessonTypes.map((type) => (
                                            <button
                                                key={type.value}
                                                type="button"
                                                className={`lesson-type-option${data.type === type.value ? ' is-active' : ''}`}
                                                onClick={() => {
                                                    clearErrors();
                                                    setData('type', type.value);
                                                }}
                                            >
                                                <i className={type.icon} />
                                                {type.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {isVideo && (
                                    <div className="col-12">
                                        <label className="form-label">الفيديو *</label>
                                        <VideoSourceField
                                            sources={videoSources}
                                            source={data.video_source}
                                            onSourceChange={(value) => setData('video_source', value)}
                                            url={data.video}
                                            onUrlChange={(value) => setData('video', value)}
                                            file={data.video_file}
                                            onFileChange={(file) => {
                                                setData('video_file', file);
                                                if (file) {
                                                    readVideoDuration(file, setDuration);
                                                }
                                            }}
                                            existingUrl={
                                                lesson?.video_source === 'upload' ? lesson.video_url : null
                                            }
                                            existingName={lesson?.video_file_name}
                                            maxMb={maxVideoUploadMb}
                                            errors={{
                                                source: fieldErrors.video_source,
                                                url: fieldErrors.video,
                                                file: fieldErrors.video_file,
                                            }}
                                        />
                                        {progress && (
                                            <div className="progress mt-2" style={{ height: 8 }}>
                                                <div
                                                    className="progress-bar bg-success"
                                                    style={{ width: `${progress.percentage}%` }}
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="col-12">
                                    <label className="form-label">
                                        {data.type === 'text' ? 'محتوى الدرس *' : 'ملاحظات / وصف الدرس'}
                                    </label>
                                    <TinyMceEditor
                                        value={data.content}
                                        onChange={(content) => setData('content', content)}
                                        height={data.type === 'text' ? 360 : 220}
                                    />
                                    {fieldErrors.content && (
                                        <div className="invalid-feedback d-block">{fieldErrors.content}</div>
                                    )}
                                </div>

                                {data.type === 'file' && (
                                    <div className="col-lg-6">
                                        <label className="form-label">ملف الدرس *</label>
                                        {existingAttachment ? (
                                            <div className="lesson-attachment">
                                                <i className="ri-attachment-2" />
                                                <a
                                                    href={lesson.attachment_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    {lesson.attachment_name}
                                                </a>
                                                <button
                                                    type="button"
                                                    className="admin-action-btn admin-action-delete ms-auto"
                                                    title="إزالة المرفق"
                                                    onClick={() => setData('remove_attachment', true)}
                                                >
                                                    <i className="ri-close-line" />
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <input
                                                    type="file"
                                                    className={`form-control${fieldErrors.attachment_file ? ' is-invalid' : ''}`}
                                                    onChange={(e) =>
                                                        setData(
                                                            'attachment_file',
                                                            e.target.files?.[0] || null,
                                                        )
                                                    }
                                                />
                                                <div className="form-text">
                                                    PDF، ZIP، Office، ملفات المشاريع… أو ضع رابطاً:
                                                </div>
                                                <input
                                                    type="url"
                                                    dir="ltr"
                                                    className={`form-control mt-1${fieldErrors.attachment ? ' is-invalid' : ''}`}
                                                    placeholder="https://..."
                                                    value={data.attachment}
                                                    onChange={(e) => setData('attachment', e.target.value)}
                                                />
                                                {(fieldErrors.attachment_file || fieldErrors.attachment) && (
                                                    <div className="invalid-feedback d-block">
                                                        {fieldErrors.attachment_file ||
                                                            fieldErrors.attachment}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                )}

                                <div className="col-lg-3">
                                    <label className="form-label">المدة</label>
                                    <div className="input-group" dir="ltr">
                                        <input
                                            type="number"
                                            min={0}
                                            className="form-control"
                                            value={data.minutes}
                                            onChange={(e) => setData('minutes', e.target.value)}
                                            aria-label="دقائق"
                                        />
                                        <span className="input-group-text">:</span>
                                        <input
                                            type="number"
                                            min={0}
                                            max={59}
                                            className="form-control"
                                            value={data.seconds}
                                            onChange={(e) => setData('seconds', e.target.value)}
                                            aria-label="ثوانٍ"
                                        />
                                    </div>
                                    <div className="form-text">
                                        دقائق : ثوانٍ{isVideo && ' — تُحسب تلقائياً عند رفع ملف'}
                                    </div>
                                    {fieldErrors.duration_seconds && (
                                        <div className="invalid-feedback d-block">
                                            {fieldErrors.duration_seconds}
                                        </div>
                                    )}
                                </div>

                                <div className="col-lg-3">
                                    <label className="form-label">الخيارات</label>
                                    <div className="form-check form-switch">
                                        <input
                                            id="lesson-published"
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={data.is_published}
                                            onChange={(e) => setData('is_published', e.target.checked)}
                                        />
                                        <label className="form-check-label" htmlFor="lesson-published">
                                            منشور
                                        </label>
                                    </div>
                                    <div className="form-check form-switch">
                                        <input
                                            id="lesson-preview"
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={data.is_preview}
                                            onChange={(e) => setData('is_preview', e.target.checked)}
                                        />
                                        <label className="form-check-label" htmlFor="lesson-preview">
                                            معاينة مجانية
                                        </label>
                                    </div>
                                </div>

                                <div className="col-12">
                                    <label className="form-label d-flex align-items-center gap-2">
                                        <i className="ri-attachment-2" /> مرفقات الدرس
                                        {attachments.length > 0 && (
                                            <span className="badge bg-primary-transparent">
                                                {attachments.length}
                                            </span>
                                        )}
                                    </label>
                                    <LessonAttachmentsField
                                        rows={attachments}
                                        onChange={setAttachments}
                                        errors={fieldErrors}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="modal-footer">
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                onClick={onClose}
                                disabled={processing}
                            >
                                إلغاء
                            </button>
                            <button
                                type="submit"
                                className="admin-btn admin-btn-primary"
                                disabled={processing}
                            >
                                <i className={processing ? 'ri-loader-4-line' : 'ri-save-line'} />
                                {processing && progress
                                    ? `جاري الرفع ${progress.percentage}%`
                                    : isEdit
                                      ? 'حفظ الدرس'
                                      : 'إضافة الدرس'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Portal>
    );
}
