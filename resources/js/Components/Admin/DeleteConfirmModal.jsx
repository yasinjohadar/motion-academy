import { useEffect } from 'react';

export default function DeleteConfirmModal({
    show,
    title = 'تأكيد الحذف',
    message,
    confirmLabel = 'حذف',
    cancelLabel = 'إلغاء',
    processing = false,
    onConfirm,
    onCancel,
}) {
    useEffect(() => {
        if (!show) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape' && !processing) {
                onCancel?.();
            }
        };

        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [show, processing, onCancel]);

    if (!show) {
        return null;
    }

    return (
        <div className="admin-delete-modal" role="dialog" aria-modal="true" aria-labelledby="admin-delete-modal-title">
            <button
                type="button"
                className="admin-delete-modal-backdrop"
                aria-label="إغلاق"
                disabled={processing}
                onClick={() => !processing && onCancel?.()}
            />

            <div className="admin-delete-modal-dialog">
                <div className="admin-delete-modal-content">
                    <div className="admin-delete-modal-icon" aria-hidden="true">
                        <i className="ri-delete-bin-6-line" />
                    </div>

                    <h4 id="admin-delete-modal-title" className="admin-delete-modal-title">
                        {title}
                    </h4>

                    <p className="admin-delete-modal-message">{message}</p>
                    <p className="admin-delete-modal-warning">لا يمكن التراجع عن هذه العملية!</p>

                    <div className="admin-delete-modal-actions">
                        <button
                            type="button"
                            className="admin-delete-modal-btn admin-delete-modal-btn-cancel"
                            disabled={processing}
                            onClick={onCancel}
                        >
                            <i className="ri-close-line" />
                            {cancelLabel}
                        </button>
                        <button
                            type="button"
                            className="admin-delete-modal-btn admin-delete-modal-btn-confirm"
                            disabled={processing}
                            onClick={onConfirm}
                        >
                            <i className="ri-delete-bin-line" />
                            {processing ? 'جاري الحذف...' : confirmLabel}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
