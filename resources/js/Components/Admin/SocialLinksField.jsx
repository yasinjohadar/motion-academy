/**
 * Repeatable `{platform, url}` rows with reordering, shared by the team member
 * form and the course landing editor. `platforms` comes from SocialPlatforms::options().
 */
export default function SocialLinksField({ links, platforms, errors, onChange, field = 'social_links', max = 8 }) {
    const update = (index, key, value) =>
        onChange(links.map((link, i) => (i === index ? { ...link, [key]: value } : link)));

    const move = (index, step) => {
        const next = [...links];
        const target = index + step;
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <div>
            {links.length === 0 && <p className="text-muted small mb-2">لا توجد روابط بعد.</p>}
            {links.map((link, index) => {
                const platform = platforms.find((option) => option.value === link.platform);
                const urlError = errors[`${field}.${index}.url`];

                return (
                    <div className="d-flex flex-wrap align-items-start gap-2 mb-2" key={index}>
                        <span
                            className="d-inline-flex align-items-center justify-content-center rounded border flex-shrink-0"
                            style={{ width: 38, height: 38 }}
                        >
                            <i className={platform?.icon || 'fas fa-link'} />
                        </span>
                        <select
                            className="form-select"
                            style={{ maxWidth: 170 }}
                            value={link.platform}
                            onChange={(e) => update(index, 'platform', e.target.value)}
                        >
                            {platforms.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <div className="flex-grow-1" style={{ minWidth: 200 }}>
                            <input
                                className={`form-control${urlError ? ' is-invalid' : ''}`}
                                dir="ltr"
                                value={link.url}
                                placeholder={link.platform === 'email' ? 'name@example.com' : 'https://...'}
                                onChange={(e) => update(index, 'url', e.target.value)}
                            />
                            {urlError && <div className="invalid-feedback">{urlError}</div>}
                        </div>
                        <div className="btn-group">
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                disabled={index === 0}
                                onClick={() => move(index, -1)}
                                aria-label="تحريك للأعلى"
                            >
                                <i className="ri-arrow-up-line" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                disabled={index === links.length - 1}
                                onClick={() => move(index, 1)}
                                aria-label="تحريك للأسفل"
                            >
                                <i className="ri-arrow-down-line" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-danger"
                                onClick={() => onChange(links.filter((_, i) => i !== index))}
                                aria-label="حذف الرابط"
                            >
                                <i className="ri-delete-bin-line" />
                            </button>
                        </div>
                    </div>
                );
            })}
            {links.length < max && (
                <button
                    type="button"
                    className="admin-btn admin-btn-ghost"
                    onClick={() => onChange([...links, { platform: platforms[0]?.value || 'linkedin', url: '' }])}
                >
                    <i className="ri-add-line" />
                    إضافة رابط
                </button>
            )}
            {errors[field] && <div className="invalid-feedback d-block">{errors[field]}</div>}
        </div>
    );
}
