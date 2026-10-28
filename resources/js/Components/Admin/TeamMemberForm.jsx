import { AdminPanel } from '@/Components/Admin/AdminUiPage';
import MediaField from '@/Components/Admin/MediaField';
import SocialLinksField from '@/Components/Admin/SocialLinksField';
import { Stars } from '@/Components/Admin/TestimonialForm';

/**
 * Initial form state for a team member; `member` is the admin payload when editing.
 */
export function teamMemberFormData(member = null, defaults = {}) {
    return {
        name: member?.name || '',
        title: member?.title || '',
        bio: member?.bio || '',
        avatar: null,
        avatar_url: '',
        avatar_preview: member?.avatar_url || '',
        remove_avatar: false,
        icon: member?.icon || 'fas fa-user-tie',
        color: member?.color || '#02ad8f',
        rating: member?.rating ?? '',
        profile_url: member?.profile_url || '',
        social_links: member?.social_links || [],
        is_active: member ? Boolean(member.is_active) : true,
        sort_order: member?.sort_order ?? defaults.sort_order ?? 0,
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

export default function TeamMemberForm({ data, setData, errors, processing, platforms, onSubmit, submitLabel = 'حفظ' }) {
    const input = (key, extra = {}) => ({
        className: `form-control${errors[key] ? ' is-invalid' : ''}`,
        value: data[key] ?? '',
        onChange: (e) => setData(key, e.target.value),
        ...extra,
    });

    const color = data.color || '#02ad8f';
    const avatarPreview = data.avatar instanceof File ? URL.createObjectURL(data.avatar) : data.avatar_preview;

    return (
        <form onSubmit={onSubmit}>
            <div className="row g-4">
                <div className="col-xl-8">
                    <AdminPanel title="بيانات العضو" icon="ri-user-star-line">
                        <div className="row g-3">
                            <Field label="الاسم" error={errors.name}>
                                <input {...input('name')} required />
                            </Field>
                            <Field label="المسمى الوظيفي" error={errors.title}>
                                <input {...input('title')} placeholder="Senior Motion Designer" />
                            </Field>
                            <Field label="نبذة قصيرة" error={errors.bio} hint={`${data.bio.length} / 500`} className="col-12">
                                <textarea {...input('bio')} rows={3} />
                            </Field>
                            <Field label="التقييم (اختياري)" error={errors.rating} hint="من 1 إلى 5، مثل 4.8">
                                <div className="d-flex align-items-center gap-2">
                                    <input
                                        {...input('rating', { type: 'number', min: 1, max: 5, step: 0.1 })}
                                        style={{ maxWidth: 110 }}
                                    />
                                    {data.rating !== '' && <Stars rating={Math.round(Number(data.rating) * 2) / 2} />}
                                </div>
                            </Field>
                            <Field
                                label="رابط الملف الشخصي (اختياري)"
                                error={errors.profile_url}
                                hint="يجعل الاسم رابطاً."
                            >
                                <input {...input('profile_url', { dir: 'ltr' })} placeholder="/instructors/ahmad" />
                            </Field>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="روابط التواصل" icon="ri-share-line" className="mt-4">
                        <SocialLinksField
                            links={data.social_links}
                            platforms={platforms}
                            errors={errors}
                            onChange={(links) => setData('social_links', links)}
                        />
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
                                hint="تظهر عند عدم وجود صورة — صنف Font Awesome مثل fas fa-user-tie"
                            >
                                <input {...input('icon', { dir: 'ltr' })} />
                            </Field>
                            <Field label="لون البطاقة" error={errors.color}>
                                <div className="d-flex gap-2">
                                    <input
                                        type="color"
                                        className="form-control form-control-color"
                                        value={color}
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
                        <div className="rounded-4 border p-4 text-center" style={{ borderBottom: `3px solid ${color}` }}>
                            <div
                                className="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center overflow-hidden"
                                style={{
                                    width: 84,
                                    height: 84,
                                    background: `${color}22`,
                                    color,
                                    fontSize: 30,
                                    border: `3px solid ${color}55`,
                                }}
                            >
                                {avatarPreview ? (
                                    <img src={avatarPreview} alt="" className="w-100 h-100 object-fit-cover" />
                                ) : (
                                    <i className={data.icon || 'fas fa-user-tie'} />
                                )}
                            </div>
                            <div className="fw-bold">{data.name || 'اسم العضو'}</div>
                            {data.title && (
                                <div className="small fw-semibold mb-2" style={{ color }}>
                                    {data.title}
                                </div>
                            )}
                            {data.rating !== '' && (
                                <div className="mb-2">
                                    <Stars rating={Math.round(Number(data.rating) * 2) / 2} />{' '}
                                    <span className="small fw-bold">{Number(data.rating).toFixed(1)}</span>
                                </div>
                            )}
                            {data.bio && <p className="small text-muted mb-2">{data.bio}</p>}
                            <div className="d-flex justify-content-center gap-2">
                                {data.social_links.map((link, index) => {
                                    const platform = platforms.find((option) => option.value === link.platform);
                                    return (
                                        <span
                                            key={index}
                                            className="d-inline-flex align-items-center justify-content-center rounded-3"
                                            style={{ width: 32, height: 32, background: `${color}18`, color }}
                                        >
                                            <i className={platform?.icon || 'fas fa-link'} />
                                        </span>
                                    );
                                })}
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="النشر والترتيب" icon="ri-settings-3-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-12">
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={Boolean(data.is_active)}
                                        onChange={(e) => setData('is_active', e.target.checked)}
                                    />
                                    <span className="form-check-label">ظاهر في الموقع</span>
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
