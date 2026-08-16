function slugify(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-');
}

export default function SitePageForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel = 'حفظ',
}) {
    return (
        <form onSubmit={onSubmit}>
            <div className="row g-3">
                <div className="col-md-8">
                    <label className="form-label">عنوان الصفحة</label>
                    <input
                        className={`form-control${errors.title ? ' is-invalid' : ''}`}
                        value={data.title}
                        onChange={(e) => {
                            const title = e.target.value;
                            setData({
                                ...data,
                                title,
                                slug: data.slug_locked ? data.slug : slugify(title),
                            });
                        }}
                        required
                    />
                    {errors.title && <div className="invalid-feedback">{errors.title}</div>}
                </div>
                <div className="col-md-4">
                    <label className="form-label">المسار (Slug)</label>
                    <input
                        className={`form-control${errors.slug ? ' is-invalid' : ''}`}
                        value={data.slug}
                        onChange={(e) =>
                            setData({
                                ...data,
                                slug: e.target.value,
                                slug_locked: true,
                            })
                        }
                        dir="ltr"
                        placeholder="about-us"
                    />
                    {errors.slug && <div className="invalid-feedback">{errors.slug}</div>}
                </div>
                <div className="col-12">
                    <label className="form-label">مقتطف قصير</label>
                    <input
                        className={`form-control${errors.excerpt ? ' is-invalid' : ''}`}
                        value={data.excerpt}
                        onChange={(e) => setData('excerpt', e.target.value)}
                        placeholder="وصف مختصر يظهر في القوائم"
                    />
                    {errors.excerpt && <div className="invalid-feedback">{errors.excerpt}</div>}
                </div>
                <div className="col-12">
                    <label className="form-label">المحتوى</label>
                    <textarea
                        className={`form-control${errors.body ? ' is-invalid' : ''}`}
                        rows={12}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        placeholder="اكتب محتوى الصفحة..."
                    />
                    {errors.body && <div className="invalid-feedback">{errors.body}</div>}
                </div>
                <div className="col-md-6">
                    <label className="form-label">عنوان SEO</label>
                    <input
                        className="form-control"
                        value={data.meta_title}
                        onChange={(e) => setData('meta_title', e.target.value)}
                    />
                </div>
                <div className="col-md-3">
                    <label className="form-label">الترتيب</label>
                    <input
                        type="number"
                        className="form-control"
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', Number(e.target.value))}
                    />
                </div>
                <div className="col-md-3 d-flex align-items-end">
                    <label className="form-check mb-2">
                        <input
                            type="checkbox"
                            className="form-check-input"
                            checked={Boolean(data.is_published)}
                            onChange={(e) => setData('is_published', e.target.checked)}
                        />
                        <span className="form-check-label">منشورة</span>
                    </label>
                </div>
                <div className="col-12">
                    <label className="form-label">وصف SEO</label>
                    <textarea
                        className="form-control"
                        rows={2}
                        value={data.meta_description}
                        onChange={(e) => setData('meta_description', e.target.value)}
                    />
                </div>
            </div>

            <div className="mt-4">
                <button type="submit" className="btn btn-primary" disabled={processing}>
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}
