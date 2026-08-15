const SCHEMA_TYPES = ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle'];

export default function BlogSchemaFields({ data, setData, fieldErrors = {} }) {
    return (
        <div className="row g-3">
            <div className="col-md-4">
                <label className="form-label">نوع Schema</label>
                <select
                    className={`form-select form-select-sm${fieldErrors.schema_type ? ' is-invalid' : ''}`}
                    value={data.schema_type || 'BlogPosting'}
                    onChange={(e) => setData('schema_type', e.target.value)}
                >
                    {SCHEMA_TYPES.map((type) => (
                        <option key={type} value={type}>
                            {type}
                        </option>
                    ))}
                </select>
                {fieldErrors.schema_type && (
                    <div className="invalid-feedback">{fieldErrors.schema_type}</div>
                )}
            </div>

            <div className="col-md-8">
                <label className="form-label">عنوان Schema</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.schema_headline ? ' is-invalid' : ''}`}
                    value={data.schema_headline || ''}
                    onChange={(e) => setData('schema_headline', e.target.value)}
                />
                {fieldErrors.schema_headline && (
                    <div className="invalid-feedback">{fieldErrors.schema_headline}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">وصف Schema</label>
                <textarea
                    rows={2}
                    className={`form-control form-control-sm${fieldErrors.schema_description ? ' is-invalid' : ''}`}
                    value={data.schema_description || ''}
                    onChange={(e) => setData('schema_description', e.target.value)}
                />
                {fieldErrors.schema_description && (
                    <div className="invalid-feedback">{fieldErrors.schema_description}</div>
                )}
            </div>

            <div className="col-12">
                <label className="form-label">صورة Schema</label>
                <input
                    type="url"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.schema_image ? ' is-invalid' : ''}`}
                    value={data.schema_image || ''}
                    onChange={(e) => setData('schema_image', e.target.value)}
                />
                {fieldErrors.schema_image && (
                    <div className="invalid-feedback">{fieldErrors.schema_image}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">اسم المؤلف</label>
                <input
                    type="text"
                    className={`form-control form-control-sm${fieldErrors.schema_author_name ? ' is-invalid' : ''}`}
                    value={data.schema_author_name || ''}
                    onChange={(e) => setData('schema_author_name', e.target.value)}
                />
                {fieldErrors.schema_author_name && (
                    <div className="invalid-feedback">{fieldErrors.schema_author_name}</div>
                )}
            </div>

            <div className="col-md-6">
                <label className="form-label">رابط المؤلف</label>
                <input
                    type="url"
                    dir="ltr"
                    className={`form-control form-control-sm${fieldErrors.schema_author_url ? ' is-invalid' : ''}`}
                    value={data.schema_author_url || ''}
                    onChange={(e) => setData('schema_author_url', e.target.value)}
                />
                {fieldErrors.schema_author_url && (
                    <div className="invalid-feedback">{fieldErrors.schema_author_url}</div>
                )}
            </div>
        </div>
    );
}
