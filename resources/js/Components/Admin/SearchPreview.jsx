/**
 * How the page will look as a Google result, with length hints that match what
 * results pages actually show (~60 characters of title, ~160 of description),
 * and the switch that keeps the page out of search results entirely.
 */
const TITLE_LIMIT = 60;
const DESCRIPTION_LIMIT = 160;

function clip(text, limit) {
    const value = (text || '').trim();

    return value.length > limit ? `${value.slice(0, limit - 1)}…` : value;
}

function Meter({ length, limit }) {
    const state = length === 0 ? 'empty' : length > limit ? 'over' : length < limit * 0.5 ? 'short' : 'good';
    const labels = {
        empty: 'فارغ — سيُستخدم البديل',
        over: 'أطول من المعروض',
        short: 'قصير',
        good: 'طول مناسب',
    };

    return (
        <span className={`seo-meter seo-meter-${state}`}>
            {length}/{limit} · {labels[state]}
        </span>
    );
}

export default function SearchPreview({
    title,
    description,
    url,
    siteName,
    indexable = true,
    onIndexableChange,
    titleLength,
    descriptionLength,
}) {
    const shownTitle = clip(siteName ? `${title} | ${siteName}` : title, TITLE_LIMIT + 10);

    return (
        <div className="seo-preview">
            <div className="seo-preview-head">
                <span className="seo-preview-label">
                    <i className="ri-google-line" /> معاينة نتيجة البحث
                </span>
                {typeof titleLength === 'number' && (
                    <span className="d-flex flex-wrap gap-2">
                        <Meter length={titleLength} limit={TITLE_LIMIT} />
                        {typeof descriptionLength === 'number' && (
                            <Meter length={descriptionLength} limit={DESCRIPTION_LIMIT} />
                        )}
                    </span>
                )}
            </div>

            <div className={`seo-serp${indexable ? '' : ' is-hidden'}`}>
                <div className="seo-serp-url" dir="ltr">
                    {url}
                </div>
                <div className="seo-serp-title">{shownTitle || 'عنوان الصفحة'}</div>
                <div className="seo-serp-desc">
                    {clip(description, DESCRIPTION_LIMIT) || 'أضف وصفاً يشرح محتوى الصفحة ويشجّع على النقر.'}
                </div>
                {!indexable && (
                    <span className="seo-serp-hidden">
                        <i className="ri-eye-off-line" /> لن تظهر في نتائج البحث
                    </span>
                )}
            </div>

            {onIndexableChange && (
                <label className="seo-index-toggle">
                    <span className="form-check form-switch m-0">
                        <input
                            type="checkbox"
                            className="form-check-input"
                            checked={indexable}
                            onChange={(e) => onIndexableChange(e.target.checked)}
                        />
                    </span>
                    <span>
                        <strong className="d-block">الظهور في محركات البحث</strong>
                        <small className="text-muted">
                            عند الإيقاف تُضاف noindex وتُستبعد الصفحة من خريطة الموقع.
                        </small>
                    </span>
                </label>
            )}
        </div>
    );
}
