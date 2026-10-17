import Portal from '@/Components/Admin/Portal';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Edit a curriculum section's title and rich-text description.
 */
export default function SectionModal({ courseId, section, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        title: section.title || '',
        description: section.description || '',
    });

    useEffect(() => {
        const onKeyDown = (event) => event.key === 'Escape' && !processing && onClose();
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [processing, onClose]);

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.courses.sections.update', [courseId, section.id]), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Portal>
            <div
                className="modal fade show d-block"
                tabIndex="-1"
                role="dialog"
                style={{ background: 'rgba(15, 23, 42, 0.55)' }}
            >
                <div className="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <form className="modal-content" onSubmit={submit}>
                        <div className="modal-header">
                            <h5 className="modal-title">
                                <i className="ri-edit-line" /> تعديل القسم
                            </h5>
                            <button
                                type="button"
                                className="btn-close"
                                onClick={onClose}
                                disabled={processing}
                            />
                        </div>
                        <div className="modal-body">
                            <div className="mb-3">
                                <label className="form-label">عنوان القسم *</label>
                                <input
                                    type="text"
                                    autoFocus
                                    className={`form-control${errors.title ? ' is-invalid' : ''}`}
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                />
                                {errors.title && <div className="invalid-feedback">{errors.title}</div>}
                            </div>
                            <label className="form-label">وصف القسم</label>
                            <TinyMceEditor
                                value={data.description}
                                onChange={(content) => setData('description', content)}
                                height={280}
                            />
                            <div className="form-text">يظهر للطلاب أعلى دروس هذا القسم (اختياري).</div>
                            {errors.description && (
                                <div className="invalid-feedback d-block">{errors.description}</div>
                            )}
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
                                حفظ القسم
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Portal>
    );
}
