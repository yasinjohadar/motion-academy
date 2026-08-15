export default function BlogSocialSeoFields({ data, setData, fieldErrors = {} }) {
    return (
        <div className="row g-3">
            <div className="col-12">
                <h6 className="mb-0 text-muted">Open Graph</h6>
            </div>

            <div className="col-md-6">
                <label className="form-label">عنوان OG</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.og_title ? ' is-invalid' : ''}`}
                    value={data.og_title || ''}
                    onChange={(e) => setData('og_title', e.target.value)}
                />
                {fieldErrors.og_title && (
                    <div className="invalid-feedback">{fieldErrors.og_title}</div>
                )}
            </div>

            <div className="col-md-3">
                <label className="form-label">نوع OG</label>
                <input
                    type="text"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.og_type ? ' is-invalid' : ''}`}
                    value={data.og_type || ''}
                    onChange={(e) => setData('og_type', e.target.value)}
                    placeholder="article"
                />
                {fieldErrors.og_type && (
                    <div className="invalid-feedback">{fieldErrors.og_type}</div>
                )}
            </div>

            <div className="col-md-3">
                <label className="form-label">لغة OG</label>
                <input
                    type="text"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.og_locale ? ' is-invalid' : ''}`}
                    value={data.og_locale || ''}
                    onChange={(e) => setData('og_locale', e.target.value)}
                    placeholder="ar_SA"
                />
                {fieldErrors.og_locale && (
                    <div className="invalid-feedback">{fieldErrors.og_locale}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">وصف OG</label>
                <textarea
                    rows={2}
                    className={`form-control form-control-sm${fieldErrors.og_description ? ' is-invalid' : ''}`}
                    value={data.og_description || ''}
                    onChange={(e) => setData('og_description', e.target.value)}
                />
                {fieldErrors.og_description && (
                    <div className="invalid-feedback">{fieldErrors.og_description}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">صورة OG</label>
                <input
                    type="url"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.og_image ? ' is-invalid' : ''}`}
                    value={data.og_image || ''}
                    onChange={(e) => setData('og_image', e.target.value)}
                />
                {fieldErrors.og_image && (
                    <div className="invalid-feedback">{fieldErrors.og_image}</div>
                )}
            </div>

            <div className="col-12 pt-2">
                <h6 className="mb-0 text-muted">تويتر</h6>
            </div>

            <div className="col-md-4">
                <label className="form-label">نوع البطاقة</label>
                <select
                    className={`form-select form-select-sm${fieldErrors.twitter_card ? ' is-invalid' : ''}`}
                    value={data.twitter_card || 'summary_large_image'}
                    onChange={(e) => setData('twitter_card', e.target.value)}
                >
                    <option value="summary">summary</option>
                    <option value="summary_large_image">summary_large_image</option>
                </select>
                {fieldErrors.twitter_card && (
                    <div className="invalid-feedback">{fieldErrors.twitter_card}</div>
                )}
            </div>

            <div className="col-md-4">
                <label className="form-label">عنوان تويتر</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.twitter_title ? ' is-invalid' : ''}`}
                    value={data.twitter_title || ''}
                    onChange={(e) => setData('twitter_title', e.target.value)}
                />
                {fieldErrors.twitter_title && (
                    <div className="invalid-feedback">{fieldErrors.twitter_title}</div>
                )}
            </div>

            <div className="col-md-4">
                <label className="form-label">منشئ تويتر</label>
                <input
                    type="text"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.twitter_creator ? ' is-invalid' : ''}`}
                    value={data.twitter_creator || ''}
                    onChange={(e) => setData('twitter_creator', e.target.value)}
                    placeholder="@username"
                />
                {fieldErrors.twitter_creator && (
                    <div className="invalid-feedback">{fieldErrors.twitter_creator}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">وصف تويتر</label>
                <textarea
                    rows={2}
                    className={`form-control form-control-sm${fieldErrors.twitter_description ? ' is-invalid' : ''}`}
                    value={data.twitter_description || ''}
                    onChange={(e) => setData('twitter_description', e.target.value)}
                />
                {fieldErrors.twitter_description && (
                    <div className="invalid-feedback">{fieldErrors.twitter_description}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">صورة تويتر</label>
                <input
                    type="url"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.twitter_image ? ' is-invalid' : ''}`}
                    value={data.twitter_image || ''}
                    onChange={(e) => setData('twitter_image', e.target.value)}
                />
                {fieldErrors.twitter_image && (
                    <div className="invalid-feedback">{fieldErrors.twitter_image}</div>
                )}
            </div>
        </div>
    );
}
