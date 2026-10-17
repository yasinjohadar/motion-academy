import { createPortal } from 'react-dom';
export default function RestoreConfirmModal({
    show,
    backupName = '',
    processing = false,
    confirmText,
    onConfirmTextChange,
    onConfirm,
    onCancel,
}) {
    if (!show) {
        return null;
    }

    const canConfirm = confirmText?.trim() === 'استرجاع';

    // Rendered into <body>: a fixed overlay inside an animated (transformed)
    // page container would be positioned against that container, not the screen.
    return createPortal(
        <div
            className="modal fade show d-block"
            tabIndex="-1"
            role="dialog"
            style={{ background: 'rgba(15, 23, 42, 0.55)' }}
        >
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title text-danger">تأكيد الاسترجاع</h5>
                        <button
                            type="button"
                            className="btn-close"
                            onClick={onCancel}
                            disabled={processing}
                        />
                    </div>
                    <div className="modal-body">
                        <p className="mb-2">
                            سيتم استبدال البيانات الحالية بمحتوى النسخة
                            {backupName ? (
                                <>
                                    {' '}
                                    «<strong>{backupName}</strong>»
                                </>
                            ) : null}
                            .
                        </p>
                        <p className="text-muted small mb-3">
                            تُنشأ لقطة أمان مؤقتة، ويُعرض تقدّم الاسترجاع مباشرة على صفحة التفاصيل. اكتب كلمة{' '}
                            <strong>استرجاع</strong> للمتابعة.
                        </p>
                        <input
                            type="text"
                            className="form-control"
                            value={confirmText}
                            onChange={(e) => onConfirmTextChange(e.target.value)}
                            placeholder="استرجاع"
                            disabled={processing}
                            autoFocus
                        />
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="admin-btn" onClick={onCancel} disabled={processing}>
                            إلغاء
                        </button>
                        <button
                            type="button"
                            className="admin-btn admin-btn-danger"
                            onClick={onConfirm}
                            disabled={!canConfirm || processing}
                        >
                            {processing ? 'جاري الاسترجاع...' : 'تأكيد الاسترجاع'}
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
