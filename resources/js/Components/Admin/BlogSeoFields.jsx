const ROBOTS_OPTIONS = [
    { value: 'index,follow', label: 'index, follow' },
    { value: 'noindex,follow', label: 'noindex, follow' },
    { value: 'index,nofollow', label: 'index, nofollow' },
    { value: 'noindex,nofollow', label: 'noindex, nofollow' },
];

export default function BlogSeoFields({ data, setData, fieldErrors = {} }) {
    return (
        <div className="row g-3">
            <div className="col-md-6">
                <label className="form-label">عنوان الميتا</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.meta_title ? ' is-invalid' : ''}`}
                    value={data.meta_title || ''}
                    onChange={(e) => setData('meta_title', e.target.value)}
                />
                {fieldErrors.meta_title && (
                    <div className="invalid-feedback">{fieldErrors.meta_title}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">الكلمة المفتاحية الرئيسية</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.focus_keyword ? ' is-invalid' : ''}`}
                    value={data.focus_keyword || ''}
                    onChange={(e) => setData('focus_keyword', e.target.value)}
                />
                {fieldErrors.focus_keyword && (
                    <div className="invalid-feedback">{fieldErrors.focus_keyword}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">وصف الميتا</label>
                <textarea
                    rows={2}
                    className={`form-control form-control-sm${fieldErrors.meta_description ? ' is-invalid' : ''}`}
                    value={data.meta_description || ''}
                    onChange={(e) => setData('meta_description', e.target.value)}
                />
                {fieldErrors.meta_description && (
                    <div className="invalid-feedback">{fieldErrors.meta_description}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">الكلمات المفتاحية</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.meta_keywords ? ' is-invalid' : ''}`}
                    value={data.meta_keywords || ''}
                    onChange={(e) => setData('meta_keywords', e.target.value)}
                    placeholder="مفصولة بفواصل"
                />
                {fieldErrors.meta_keywords && (
                    <div className="invalid-feedback">{fieldErrors.meta_keywords}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">مرادفات الكلمة المفتاحية</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.focus_keyword_synonyms ? ' is-invalid' : ''}`}
                    value={data.focus_keyword_synonyms || ''}
                    onChange={(e) => setData('focus_keyword_synonyms', e.target.value)}
                />
                {fieldErrors.focus_keyword_synonyms && (
                    <div className="invalid-feedback">{fieldErrors.focus_keyword_synonyms}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">الرابط القانوني (Canonical)</label>
                <input
                    type="url"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.canonical_url ? ' is-invalid' : ''}`}
                    value={data.canonical_url || ''}
                    onChange={(e) => setData('canonical_url', e.target.value)}
                />
                {fieldErrors.canonical_url && (
                    <div className="invalid-feedback">{fieldErrors.canonical_url}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">روبوتات الميتا</label>
                <select
                    className={`form-select form-select-sm${fieldErrors.robots_meta ? ' is-invalid' : ''}`}
                    value={data.robots_meta || 'index,follow'}
                    onChange={(e) => setData('robots_meta', e.target.value)}
                >
                    {ROBOTS_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {fieldErrors.robots_meta && (
                    <div className="invalid-feedback">{fieldErrors.robots_meta}</div>
                )}
            </div>

            <div className="col-12 d-flex flex-wrap gap-4">
                <label className="d-inline-flex align-items-center gap-2 mb-0">
                    <input
                        type="checkbox"
                        checked={Boolean(data.is_indexable)}
                        onChange={(e) => setData('is_indexable', e.target.checked)}
                    />
                    قابل للفهرسة
                </label>
                <label className="d-inline-flex align-items-center gap-2 mb-0">
                    <input
                        type="checkbox"
                        checked={Boolean(data.is_followable)}
                        onChange={(e) => setData('is_followable', e.target.checked)}
                    />
                    متابعة الروابط
                </label>
            </div>
        </div>
    );
}
