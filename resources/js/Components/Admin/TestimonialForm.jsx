import { AdminPanel } from '@/Components/Admin/AdminUiPage';
import MediaField from '@/Components/Admin/MediaField';

const RATINGS = [5, 4.5, 4, 3.5, 3, 2.5, 2, 1.5, 1];

/**
 * Initial form state for a testimonial; `testimonial` is the admin payload when editing.
 */
export function testimonialFormData(testimonial = null, defaults = {}) {
    return {
        name: testimonial?.name || '',
        role: testimonial?.role || '',
        company: testimonial?.company || '',
        content: testimonial?.content || '',
        rating: testimonial?.rating ?? 5,
        avatar: null,
        avatar_url: '',
        avatar_preview: testimonial?.avatar_url || '',
        remove_avatar: false,
        icon: testimonial?.icon || 'fas fa-user',
        color: testimonial?.color || '#02ad8f',
        course_id: testimonial?.course_id ?? '',
        is_featured: Boolean(testimonial?.is_featured),
        is_active: testimonial ? Boolean(testimonial.is_active) : true,
        sort_order: testimonial?.sort_order ?? defaults.sort_order ?? 0,
    };
}

export function Stars({ rating, size = 14 }) {
    return (
        <span className="d-inline-flex gap-1" style={{ color: '#f5a524', fontSize: size }} dir="ltr">
            {[1, 2, 3, 4, 5].map((step) => (
                <i
                    key={step}
                    className={
                        rating >= step ? 'ri-star-fill' : rating >= step - 0.5 ? 'ri-star-half-line' : 'ri-star-line'
                    }
                />
            ))}
        </span>
    );
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

export default function TestimonialForm({ data, setData, errors, processing, courses, onSubmit, submitLabel = 'حفظ' }) {
    const input = (key, extra = {}) => ({
        className: `form-control${errors[key] ? ' is-invalid' : ''}`,
        value: data[key] ?? '',
        onChange: (e) => setData(key, e.target.value),
        ...extra,
    });

    const avatarPreview = data.avatar instanceof File ? URL.createObjectURL(data.avatar) : data.avatar_preview;
    const subtitle = [data.role, data.company].filter(Boolean).join(' · ');

    return (
        <form onSubmit={onSubmit}>
            <div className="row g-4">
                <div className="col-xl-8">
                    <AdminPanel title="صاحب الرأي" icon="ri-user-star-line">
                        <div className="row g-3">
                            <Field label="الاسم" error={errors.name} className="col-md-4">
                                <input {...input('name')} required />
                            </Field>
                            <Field label="المسمى الوظيفي" error={errors.role} className="col-md-4">
                                <input {...input('role')} placeholder="Motion Designer" />
                            </Field>
                            <Field label="الجهة / الشركة (اختياري)" error={errors.company} className="col-md-4">
                                <input {...input('company')} />
                            </Field>
                            <Field
                                label="نص الرأي"
                                error={errors.content}
                                hint={`${data.content.length} / 1000`}
                                className="col-12"
                            >
                                <textarea {...input('content')} rows={4} required />
                            </Field>
                            <Field label="التقييم" error={errors.rating}>
                                <div className="d-flex align-items-center gap-2">
                                    <select
                                        className={`form-select${errors.rating ? ' is-invalid' : ''}`}
                                        style={{ maxWidth: 120 }}
                                        value={data.rating}
                                        onChange={(e) => setData('rating', Number(e.target.value))}
                                    >
                                        {RATINGS.map((value) => (
                                            <option key={value} value={value}>
                                                {value}
                                            </option>
                                        ))}
                                    </select>
                                    <Stars rating={Number(data.rating)} size={16} />
                                </div>
                            </Field>
                            <Field label="الدورة التي درسها (اختياري)" error={errors.course_id} hint="تظهر كرابط أسفل الاسم إن كانت منشورة.">
                                <select
                                    className={`form-select${errors.course_id ? ' is-invalid' : ''}`}
                                    value={data.course_id}
                                    onChange={(e) => setData('course_id', e.target.value)}
                                >
                                    <option value="">— بدون —</option>
                                    {courses.map((course) => (
                                        <option key={course.id} value={course.id}>
                                            {course.title}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="الصورة والمظهر" icon="ri-palette-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-12">
                                <MediaField
                                    label="الصورة الشخصية (اختياري)"
                                    previewUrl={avatarPreview}
                                    alt={data.name}
                                    error={errors.avatar || errors.avatar_url}
                                    onPick={(item) =>
                                        setData({
                                            ...data,
                                            avatar: null,
                                            avatar_url: item.url,
                                            avatar_preview: item.url,
                                            remove_avatar: false,
                                        })
                                    }
                                    onFile={(file) =>
                                        setData({ ...data, avatar: file, avatar_url: '', avatar_preview: '', remove_avatar: false })
                                    }
                                    onClear={() =>
                                        setData({ ...data, avatar: null, avatar_url: '', avatar_preview: '', remove_avatar: true })
                                    }
                                />
                            </div>
                            <Field
                                label="الأيقونة البديلة"
                                error={errors.icon}
                                hint="تظهر عند عدم وجود صورة — صنف Font Awesome مثل fas fa-user"
                            >
                                <input {...input('icon', { dir: 'ltr' })} />
                            </Field>
                            <Field label="لون البطاقة" error={errors.color}>
                                <div className="d-flex gap-2">
                                    <input
                                        type="color"
                                        className="form-control form-control-color"
                                        value={data.color || '#02ad8f'}
                                        onChange={(e) => setData('color', e.target.value)}
                                    />
                                    <input {...input('color', { dir: 'ltr', maxLength: 7 })} />
                                </div>
                            </Field>
                        </div>
                    </AdminPanel>
                </div>

                <div className="col-xl-4">
                    <AdminPanel title="معاينة" icon="ri-eye-line">
                        <div
                            className="rounded-4 border p-4 text-center position-relative"
                            style={{ borderBottom: `3px solid ${data.color || '#02ad8f'}` }}
                        >
                            <div
                                className="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center overflow-hidden"
                                style={{
                                    width: 72,
                                    height: 72,
                                    background: `${data.color || '#02ad8f'}22`,
                                    color: data.color || '#02ad8f',
                                    fontSize: 26,
                                    border: `3px solid ${data.color || '#02ad8f'}55`,
                                }}
                            >
                                {avatarPreview ? (
                                    <img src={avatarPreview} alt="" className="w-100 h-100 object-fit-cover" />
                                ) : (
                                    <i className={data.icon || 'fas fa-user'} />
                                )}
                            </div>
                            <p className="small text-muted mb-3">“{data.content || 'نص الرأي يظهر هنا'}”</p>
                            <Stars rating={Number(data.rating)} />
                            <div className="fw-bold mt-2">{data.name || 'اسم المتعلم'}</div>
                            {subtitle && (
                                <div className="small fw-semibold" style={{ color: data.color || '#02ad8f' }}>
                                    {subtitle}
                                </div>
                            )}
                        </div>
                    </AdminPanel>

                    <AdminPanel title="النشر والترتيب" icon="ri-settings-3-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-12 d-flex flex-column gap-2">
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(data.is_active)}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                    />
                                    <span className="form-check-label">ظاهر في الموقع</span>
                                </label>
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(data.is_featured)}
                                        onChange={(e) => setData('is_featured', e.target.checked)}
                                    />
                                    <span className="form-check-label">رأي مميز</span>
                                </label>
                            </div>
                            <Field label="الترتيب" error={errors.sort_order} className="col-12">
                                <input {...input('sort_order', { type: 'number', min: 0 })} />
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
