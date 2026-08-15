import MediaPicker from '@/Components/Admin/MediaPicker';
import { useState } from 'react';

/**
 * Image field with library picker + optional direct file upload.
 * - onPick({ id, url, alt, title, ... }) when choosing from library
 * - onFile(File|null) when uploading a local file (legacy form path)
 */
export default function MediaField({
    label = 'صورة',
    previewUrl = null,
    alt = '',
    error = null,
    onPick,
    onFile,
    onClear,
    allowUpload = true,
    disabled = false,
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="mb-3">
            {label && <label className="form-label">{label}</label>}
            <div className="d-flex flex-wrap gap-2">
                <button
                    type="button"
                    className="admin-btn admin-btn-primary"
                    disabled={disabled}
                    onClick={() => setOpen(true)}
                >
                    <i className="ri-gallery-line" />
                    من المكتبة
                </button>
                {allowUpload && (
                    <label className={`admin-btn admin-btn-ghost mb-0${disabled ? ' disabled' : ''}`}>
                        <i className="ri-upload-2-line" />
                        رفع ملف
                        <input
                            type="file"
                            accept="image/*"
                            hidden
                            disabled={disabled}
                            onChange={(e) => onFile?.(e.target.files?.[0] || null)}
                        />
                    </label>
                )}
                {(previewUrl || onClear) && (
                    <button
                        type="button"
                        className="admin-btn admin-btn-danger"
                        disabled={disabled || !previewUrl}
                        onClick={() => onClear?.()}
                    >
                        إزالة
                    </button>
                )}
            </div>
            {previewUrl && (
                <div className="media-field-preview">
                    <img src={previewUrl} alt={alt || ''} />
                    <span className="small text-muted text-break" dir="ltr">
                        {previewUrl}
                    </span>
                </div>
            )}
            {error && <div className="invalid-feedback d-block">{error}</div>}

            <MediaPicker
                open={open}
                onClose={() => setOpen(false)}
                onSelect={(item) => onPick?.(item)}
            />
        </div>
    );
}
