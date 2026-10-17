import { useRef } from 'react';

let nextKey = 0;
const key = () => `new-${(nextKey += 1)}`;

/** Existing attachments from the server → editable rows. */
export function attachmentRows(attachments = []) {
    return attachments.map((item) => ({
        key: `id-${item.id}`,
        id: item.id,
        type: item.type,
        title: item.title || '',
        url: item.url || '',
        file: null,
        file_name: item.file_name,
        size: item.size,
        href: item.href,
        icon: item.icon,
    }));
}

/** Rows → the fields the server validates (no display-only keys). */
export function attachmentPayload(rows) {
    return rows.map(({ id, type, title, url, file }) => ({
        id: id ?? '',
        type,
        title,
        url: type === 'link' ? url : '',
        file: type === 'file' ? file : null,
    }));
}

function titleFromFile(name) {
    return name
        .replace(/\.[^.]+$/, '')
        .replace(/[-_]+/g, ' ')
        .trim();
}

/**
 * Lesson resources: any number of named links and files, reorderable.
 * Drop files anywhere on the box to add them in one go.
 */
export default function LessonAttachmentsField({ rows, onChange, errors = {} }) {
    const fileInput = useRef(null);

    const update = (index, patch) =>
        onChange(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    const remove = (index) => onChange(rows.filter((_, i) => i !== index));
    const move = (index, step) => {
        const target = index + step;
        if (target < 0 || target >= rows.length) {
            return;
        }
        const next = [...rows];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    const addLink = () =>
        onChange([...rows, { key: key(), id: null, type: 'link', title: '', url: '', file: null }]);

    const addFiles = (files) => {
        const added = Array.from(files || []).map((file) => ({
            key: key(),
            id: null,
            type: 'file',
            title: titleFromFile(file.name),
            url: '',
            file,
        }));
        if (added.length) {
            onChange([...rows, ...added]);
        }
    };

    const error = (index, field) => errors[`attachments.${index}.${field}`];

    return (
        <div
            className="latt-box"
            onDragOver={(e) => {
                e.preventDefault();
                e.currentTarget.classList.add('is-drop');
            }}
            onDragLeave={(e) => e.currentTarget.classList.remove('is-drop')}
            onDrop={(e) => {
                e.preventDefault();
                e.currentTarget.classList.remove('is-drop');
                addFiles(e.dataTransfer.files);
            }}
        >
            {rows.length === 0 && (
                <div className="latt-empty">
                    <i className="ri-attachment-2" />
                    <span>
                        لا مرفقات بعد — أضف روابط أو ملفات يحتاجها الطالب في هذا الدرس، أو اسحب الملفات إلى
                        هنا.
                    </span>
                </div>
            )}

            {rows.map((row, index) => (
                <div key={row.key} className={`latt-row is-${row.type}`}>
                    <span className="latt-icon">
                        <i
                            className={
                                row.type === 'link' ? row.icon || 'ri-link' : row.icon || 'ri-file-3-line'
                            }
                        />
                    </span>

                    <div className="latt-fields">
                        <input
                            className={`form-control form-control-sm${error(index, 'title') ? ' is-invalid' : ''}`}
                            placeholder={
                                row.type === 'link' ? 'اسم الرابط — مثال: ملفات المشروع' : 'اسم الملف'
                            }
                            value={row.title}
                            onChange={(e) => update(index, { title: e.target.value })}
                        />
                        {row.type === 'link' ? (
                            <input
                                type="url"
                                dir="ltr"
                                className={`form-control form-control-sm${error(index, 'url') ? ' is-invalid' : ''}`}
                                placeholder="https://..."
                                value={row.url}
                                onChange={(e) => update(index, { url: e.target.value })}
                            />
                        ) : (
                            <div className={`latt-file${error(index, 'file') ? ' is-invalid' : ''}`}>
                                {row.file ? (
                                    <span className="latt-file-name" dir="ltr">
                                        <i className="ri-upload-2-line" /> {row.file.name}{' '}
                                        <small>({Math.max(1, Math.round(row.file.size / 1024))} KB)</small>
                                    </span>
                                ) : row.file_name ? (
                                    <a
                                        href={row.href}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="latt-file-name"
                                        dir="ltr"
                                    >
                                        <i className="ri-download-2-line" /> {row.file_name}
                                        {row.size && <small> ({row.size})</small>}
                                    </a>
                                ) : (
                                    <span className="latt-file-name text-muted">لم يُختر ملف</span>
                                )}
                                <label className="latt-file-pick">
                                    {row.file || row.file_name ? 'استبدال' : 'اختيار ملف'}
                                    <input
                                        type="file"
                                        style={{ display: 'none' }}
                                        onChange={(e) => update(index, { file: e.target.files?.[0] || null })}
                                    />
                                </label>
                            </div>
                        )}
                        {(error(index, 'title') || error(index, 'url') || error(index, 'file')) && (
                            <div className="invalid-feedback d-block">
                                {error(index, 'title') || error(index, 'url') || error(index, 'file')}
                            </div>
                        )}
                    </div>

                    <div className="latt-actions">
                        <button
                            type="button"
                            className="latt-btn"
                            title="لأعلى"
                            disabled={index === 0}
                            onClick={() => move(index, -1)}
                        >
                            <i className="ri-arrow-up-s-line" />
                        </button>
                        <button
                            type="button"
                            className="latt-btn"
                            title="لأسفل"
                            disabled={index === rows.length - 1}
                            onClick={() => move(index, 1)}
                        >
                            <i className="ri-arrow-down-s-line" />
                        </button>
                        <button
                            type="button"
                            className="latt-btn is-danger"
                            title="حذف"
                            onClick={() => remove(index)}
                        >
                            <i className="ri-delete-bin-line" />
                        </button>
                    </div>
                </div>
            ))}

            <div className="latt-add">
                <button type="button" className="latt-add-btn" onClick={addLink}>
                    <i className="ri-link" /> إضافة رابط
                </button>
                <button type="button" className="latt-add-btn" onClick={() => fileInput.current?.click()}>
                    <i className="ri-upload-cloud-2-line" /> إضافة ملفات
                </button>
                <input
                    ref={fileInput}
                    type="file"
                    multiple
                    style={{ display: 'none' }}
                    onChange={(e) => {
                        addFiles(e.target.files);
                        e.target.value = '';
                    }}
                />
                <span className="latt-hint">PDF، ZIP، Office، صور، ملفات المشاريع — حتى 50MB للملف.</span>
            </div>
        </div>
    );
}
