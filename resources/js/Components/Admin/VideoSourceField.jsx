import { useEffect, useMemo } from 'react';

const PLACEHOLDERS = {
    youtube: 'https://www.youtube.com/watch?v=...',
    vimeo: 'https://vimeo.com/123456789',
    url: 'https://cdn.example.com/video.mp4',
};

const SOURCE_ICONS = {
    youtube: 'ri-youtube-line',
    vimeo: 'ri-vimeo-line',
    url: 'ri-links-line',
    upload: 'ri-upload-cloud-2-line',
};

function embedUrl(source, url) {
    if (!url) {
        return null;
    }
    if (source === 'youtube') {
        const match = url.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([\w-]{6,})/);
        return match ? `https://www.youtube.com/embed/${match[1]}` : null;
    }
    if (source === 'vimeo') {
        const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        return match ? `https://player.vimeo.com/video/${match[1]}` : null;
    }
    return null;
}

/**
 * Video picker for a lesson or a course intro: source tabs, then a link or a file,
 * with a live preview. `existingUrl` / `existingName` describe an already-stored upload.
 */
export default function VideoSourceField({
    sources = [],
    source,
    onSourceChange,
    url,
    onUrlChange,
    file,
    onFileChange,
    existingUrl = null,
    existingName = null,
    maxMb = null,
    errors = {},
    allowNone = false,
}) {
    const objectUrl = useMemo(() => (file instanceof File ? URL.createObjectURL(file) : null), [file]);
    useEffect(() => () => objectUrl && URL.revokeObjectURL(objectUrl), [objectUrl]);

    const embed = embedUrl(source, url);
    const playable = source === 'upload' ? objectUrl || existingUrl : source === 'url' ? url : null;

    return (
        <div className="video-source-field">
            <div className="video-source-tabs" role="tablist">
                {allowNone && (
                    <button
                        type="button"
                        className={`video-source-tab${!source ? ' is-active' : ''}`}
                        onClick={() => onSourceChange('')}
                    >
                        <i className="ri-forbid-line" />
                        بدون
                    </button>
                )}
                {sources.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        className={`video-source-tab${source === option.value ? ' is-active' : ''}`}
                        onClick={() => onSourceChange(option.value)}
                    >
                        <i className={SOURCE_ICONS[option.value]} />
                        {option.label}
                    </button>
                ))}
            </div>
            {errors.source && <div className="invalid-feedback d-block">{errors.source}</div>}

            {source && source !== 'upload' && (
                <div className="mt-2">
                    <input
                        type="url"
                        dir="ltr"
                        className={`form-control${errors.url ? ' is-invalid' : ''}`}
                        placeholder={PLACEHOLDERS[source]}
                        value={url || ''}
                        onChange={(e) => onUrlChange(e.target.value)}
                    />
                    {errors.url && <div className="invalid-feedback">{errors.url}</div>}
                </div>
            )}

            {source === 'upload' && (
                <div className="mt-2">
                    <label className={`video-dropzone${errors.file ? ' is-invalid' : ''}`}>
                        <i className="ri-upload-cloud-2-line" />
                        <span>
                            {file instanceof File
                                ? `${file.name} (${(file.size / 1024 / 1024).toFixed(1)} MB)`
                                : existingName
                                  ? `الملف الحالي: ${existingName} — اختر ملفاً لاستبداله`
                                  : 'اضغط لاختيار ملف فيديو (MP4 / WebM / MOV)'}
                        </span>
                        {maxMb && <small className="text-muted">الحد الأقصى {maxMb} MB</small>}
                        <input
                            type="file"
                            accept="video/mp4,video/webm,video/quicktime"
                            hidden
                            onChange={(e) => onFileChange(e.target.files?.[0] || null)}
                        />
                    </label>
                    {errors.file && <div className="invalid-feedback d-block">{errors.file}</div>}
                </div>
            )}

            {(embed || playable) && (
                <div className="video-preview mt-2">
                    {embed ? (
                        <iframe src={embed} title="معاينة الفيديو" allowFullScreen />
                    ) : (
                        <video src={playable} controls preload="metadata" />
                    )}
                </div>
            )}
        </div>
    );
}
