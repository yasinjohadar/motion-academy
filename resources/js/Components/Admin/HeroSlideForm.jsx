import { AdminPanel } from '@/Components/Admin/AdminUiPage';
import MediaField from '@/Components/Admin/MediaField';

/**
 * Initial form state for a hero slide; `slide` is the admin payload when editing.
 */
export function heroSlideFormData(slide = null, defaults = {}) {
    return {
        badge_text: slide?.badge_text || '',
        badge_icon: slide?.badge_icon || '',
        title: slide?.title || '',
        title_highlight: slide?.title_highlight || '',
        description: slide?.description || '',
        primary_button_label: slide?.primary_button_label || '',
        primary_button_url: slide?.primary_button_url || '',
        primary_button_icon: slide?.primary_button_icon || 'fas fa-arrow-left',
        secondary_button_label: slide?.secondary_button_label || '',
        secondary_button_url: slide?.secondary_button_url || '',
        secondary_button_icon: slide?.secondary_button_icon || '',
        bullet_label: slide?.bullet_label || '',
        visual: slide?.visual || 'none',
        image: null,
        image_url: '',
        image_preview: slide?.image_url || '',
        remove_image: false,
        image_alt: slide?.image_alt || '',
        show_stats: Boolean(slide?.show_stats),
        is_active: slide ? Boolean(slide.is_active) : true,
        sort_order: slide?.sort_order ?? defaults.sort_order ?? 0,
        starts_at: slide?.starts_at || '',
        ends_at: slide?.ends_at || '',
    };
}

function Field({ label, error, hint, className = 'col-md-6', children }) {
    return (
        <div className={className}>
            <label className="form-label">{label}</label>
            {children}
            {hint && <div className="form-text">{hint}</div>}
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </div>
    );
}

function TitlePreview({ title, highlight }) {
    const text = title || 'عنوان الشريحة';
    const index = highlight ? text.indexOf(highlight) : -1;

    const lines = (value) =>
        value.split('\n').map((line, i, all) => (
            <span key={i}>
                {line}
                {i < all.length - 1 && <br />}
            </span>
        ));

    if (index === -1) {
        return lines(text);
    }

    return (
        <>
            {lines(text.slice(0, index))}
            <span
                style={{
                    background: 'linear-gradient(90deg, #02ad8f, #45bec4, #1a83c1)',
                    WebkitBackgroundClip: 'text',
                    color: 'transparent',
                }}
            >
                {highlight}
            </span>
            {lines(text.slice(index + highlight.length))}
        </>
    );
}

export default function HeroSlideForm({
    data,
    setData,
    errors,
    processing,
    visuals,
    onSubmit,
    submitLabel = 'حفظ',
}) {
    const input = (key, extra = {}) => ({
        className: `form-control${errors[key] ? ' is-invalid' : ''}`,
        value: data[key] ?? '',
        onChange: (e) => setData(key, e.target.value),
        ...extra,
    });

    const highlightMissing =
        data.title_highlight && data.title && !data.title.includes(data.title_highlight);

    return (
        <form onSubmit={onSubmit}>
            <div className="row g-4">
                <div className="col-xl-8">
                    <AdminPanel title="محتوى الشريحة" icon="ri-text">
                        <div className="row g-3">
                            <Field label="نص الشارة" error={errors.badge_text}>
                                <input {...input('badge_text')} placeholder="ابدأ الآن" />
                            </Field>
                            <Field
                                label="أيقونة الشارة"
                                error={errors.badge_icon}
                                hint="صنف Font Awesome مثل fas fa-play"
                            >
                                <input {...input('badge_icon', { dir: 'ltr' })} placeholder="fas fa-play" />
                            </Field>
                            <Field
                                label="العنوان"
                                error={errors.title}
                                hint="اضغط Enter لكسر السطر."
                                className="col-md-8"
                            >
                                <textarea {...input('title')} rows={2} required />
                            </Field>
                            <Field
                                label="الجزء المميز (بتدرج لوني)"
                                error={errors.title_highlight}
                                hint={
                                    highlightMissing
                                        ? 'هذا النص غير موجود في العنوان، لن يُميَّز.'
                                        : 'كلمة أو عبارة من العنوان.'
                                }
                                className="col-md-4"
                            >
                                <input {...input('title_highlight')} />
                            </Field>
                            <Field label="الوصف" error={errors.description} className="col-12">
                                <textarea {...input('description')} rows={3} />
                            </Field>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="الأزرار" icon="ri-cursor-line" className="mt-4">
                        <div className="row g-3">
                            {[
                                ['primary', 'الزر الرئيسي'],
                                ['secondary', 'الزر الثانوي'],
                            ].map(([key, label]) => (
                                <div className="col-12" key={key}>
                                    <h6 className="mb-2">{label}</h6>
                                    <div className="row g-2">
                                        <Field label="النص" error={errors[`${key}_button_label`]} className="col-md-4">
                                            <input {...input(`${key}_button_label`)} />
                                        </Field>
                                        <Field
                                            label="الرابط"
                                            error={errors[`${key}_button_url`]}
                                            hint="/courses أو https://..."
                                            className="col-md-5"
                                        >
                                            <input {...input(`${key}_button_url`, { dir: 'ltr' })} />
                                        </Field>
                                        <Field label="الأيقونة" error={errors[`${key}_button_icon`]} className="col-md-3">
                                            <input {...input(`${key}_button_icon`, { dir: 'ltr' })} />
                                        </Field>
                                    </div>
                                </div>
                            ))}
                            <div className="col-12 form-text mt-0">اترك النص والرابط فارغين لإخفاء الزر.</div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="العنصر المرئي" icon="ri-image-2-line" className="mt-4">
                        <div className="row g-3">
                            <Field label="نوع العنصر" error={errors.visual}>
                                <select
                                    className={`form-select${errors.visual ? ' is-invalid' : ''}`}
                                    value={data.visual}
                                    onChange={(e) => setData('visual', e.target.value)}
                                >
                                    {visuals.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <div className="col-md-6 d-flex align-items-end">
                                <label className="form-check mb-2">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(data.show_stats)}
                                        onChange={(e) => setData('show_stats', e.target.checked)}
                                    />
                                    <span className="form-check-label">إظهار إحصائيات الدورات تحت الأزرار</span>
                                </label>
                            </div>
                            {data.visual === 'image' && (
                                <>
                                    <div className="col-12">
                                        <MediaField
                                            label="صورة الشريحة"
                                            previewUrl={
                                                data.image instanceof File
                                                    ? URL.createObjectURL(data.image)
                                                    : data.image_preview
                                            }
                                            alt={data.image_alt}
                                            error={errors.image || errors.image_url}
                                            onPick={(item) =>
                                                setData({
                                                    ...data,
                                                    image: null,
                                                    image_url: item.url,
                                                    image_preview: item.url,
                                                    remove_image: false,
                                                    image_alt: data.image_alt || item.alt || '',
                                                })
                                            }
                                            onFile={(file) =>
                                                setData({
                                                    ...data,
                                                    image: file,
                                                    image_url: '',
                                                    image_preview: '',
                                                    remove_image: false,
                                                })
                                            }
                                            onClear={() =>
                                                setData({
                                                    ...data,
                                                    image: null,
                                                    image_url: '',
                                                    image_preview: '',
                                                    remove_image: true,
                                                })
                                            }
                                        />
                                    </div>
                                    <Field label="النص البديل للصورة" error={errors.image_alt} className="col-12">
                                        <input {...input('image_alt')} />
                                    </Field>
                                </>
                            )}
                        </div>
                    </AdminPanel>
                </div>

                <div className="col-xl-4">
                    <AdminPanel title="معاينة" icon="ri-eye-line">
                        <div
                            className="rounded-3 p-4 text-center"
                            style={{ background: 'linear-gradient(160deg, #f3fbfb, #eaf3fb)' }}
                        >
                            {data.badge_text && (
                                <span className="badge rounded-pill bg-white text-dark border mb-3">
                                    {data.badge_text}
                                </span>
                            )}
                            <h4 className="fw-bold mb-2">
                                <TitlePreview title={data.title} highlight={data.title_highlight} />
                            </h4>
                            {data.description && <p className="small text-muted mb-3">{data.description}</p>}
                            <div className="d-flex flex-wrap gap-2 justify-content-center">
                                {data.primary_button_label && (
                                    <span className="btn btn-sm btn-primary">{data.primary_button_label}</span>
                                )}
                                {data.secondary_button_label && (
                                    <span className="btn btn-sm btn-outline-secondary">
                                        {data.secondary_button_label}
                                    </span>
                                )}
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="النشر والترتيب" icon="ri-calendar-schedule-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-12">
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(data.is_active)}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                    />
                                    <span className="form-check-label">مفعّلة</span>
                                </label>
                            </div>
                            <Field label="الترتيب" error={errors.sort_order}>
                                <input {...input('sort_order', { type: 'number', min: 0 })} />
                            </Field>
                            <Field
                                label="عنوان النقطة"
                                error={errors.bullet_label}
                                hint="يظهر أسفل السلايدر"
                            >
                                <input {...input('bullet_label')} />
                            </Field>
                            <Field label="تبدأ في (اختياري)" error={errors.starts_at} className="col-12">
                                <input {...input('starts_at', { type: 'datetime-local' })} />
                            </Field>
                            <Field label="تنتهي في (اختياري)" error={errors.ends_at} className="col-12">
                                <input {...input('ends_at', { type: 'datetime-local' })} />
                            </Field>
                        </div>
                    </AdminPanel>

                    <button type="submit" className="admin-btn admin-btn-primary w-100 mt-4" disabled={processing}>
                        <i className="ri-save-line" />
                        {processing ? 'جارٍ الحفظ...' : submitLabel}
                    </button>
                </div>
            </div>
        </form>
    );
}
