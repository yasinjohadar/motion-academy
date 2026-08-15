import IconPicker from '@/Components/Admin/IconPicker';

const emptyLink = (index = 0) => ({
    id: null,
    label: '',
    url: '',
    icon: 'ri-link',
    sort_order: index,
    is_active: true,
});

export default function SocialLinksEditor({
    links = [],
    iconOptions = [],
    errors = {},
    onChange,
}) {
    const update = (index, field, value) => {
        const next = links.map((link, i) =>
            i === index ? { ...link, [field]: value } : link,
        );
        onChange(next);
    };

    const add = () => {
        onChange([...links, emptyLink(links.length)]);
    };

    const remove = (index) => {
        onChange(links.filter((_, i) => i !== index).map((link, i) => ({
            ...link,
            sort_order: i,
        })));
    };

    const move = (index, direction) => {
        const target = index + direction;
        if (target < 0 || target >= links.length) {
            return;
        }
        const next = [...links];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next.map((link, i) => ({ ...link, sort_order: i })));
    };

    return (
        <div className="social-links-editor">
            <div className="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 className="mb-1">وسائل التواصل</h5>
                    <p className="text-muted small mb-0">أضف أي عدد من الروابط مع الأيقونة المناسبة</p>
                </div>
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={add}>
                    <i className="ri-add-line" /> إضافة رابط
                </button>
            </div>

            {links.length === 0 && (
                <div className="text-muted border rounded p-3 text-center mb-2">
                    لا توجد روابط بعد. اضغط «إضافة رابط».
                </div>
            )}

            <div className="d-flex flex-column gap-3">
                {links.map((link, index) => (
                    <div key={link.id || `new-${index}`} className="border rounded p-3 bg-light-subtle">
                        <div className="d-flex justify-content-between align-items-center mb-2">
                            <strong className="small">رابط #{index + 1}</strong>
                            <div className="d-flex gap-1">
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => move(index, -1)}
                                    disabled={index === 0}
                                    title="أعلى"
                                >
                                    <i className="ri-arrow-up-line" />
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => move(index, 1)}
                                    disabled={index === links.length - 1}
                                    title="أسفل"
                                >
                                    <i className="ri-arrow-down-line" />
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-danger"
                                    onClick={() => remove(index)}
                                >
                                    <i className="ri-delete-bin-line" />
                                </button>
                            </div>
                        </div>

                        <div className="row g-2">
                            <div className="col-md-4">
                                <label className="form-label">الاسم</label>
                                <input
                                    className={`form-control${errors[`social_links.${index}.label`] ? ' is-invalid' : ''}`}
                                    value={link.label}
                                    onChange={(e) => update(index, 'label', e.target.value)}
                                    placeholder="إنستغرام"
                                />
                                {errors[`social_links.${index}.label`] && (
                                    <div className="invalid-feedback">
                                        {errors[`social_links.${index}.label`]}
                                    </div>
                                )}
                            </div>
                            <div className="col-md-5">
                                <label className="form-label">الرابط</label>
                                <input
                                    className={`form-control${errors[`social_links.${index}.url`] ? ' is-invalid' : ''}`}
                                    value={link.url}
                                    onChange={(e) => update(index, 'url', e.target.value)}
                                    placeholder="https://"
                                    dir="ltr"
                                />
                                {errors[`social_links.${index}.url`] && (
                                    <div className="invalid-feedback">
                                        {errors[`social_links.${index}.url`]}
                                    </div>
                                )}
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">الأيقونة</label>
                                <IconPicker
                                    value={link.icon}
                                    options={iconOptions}
                                    onChange={(val) => update(index, 'icon', val)}
                                    error={errors[`social_links.${index}.icon`]}
                                />
                            </div>
                            <div className="col-12">
                                <label className="form-check">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(link.is_active)}
                                        onChange={(e) => update(index, 'is_active', e.target.checked)}
                                    />
                                    <span className="form-check-label">نشط</span>
                                </label>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export { emptyLink };
