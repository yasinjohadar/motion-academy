import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import ListEditor from '@/Components/Admin/ListEditor';
import RepeaterField from '@/Components/Admin/RepeaterField';
import SocialLinksField from '@/Components/Admin/SocialLinksField';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

/** Same patterns the server accepts (VideoSource::Youtube). */
function youtubeId(url) {
    const match = String(url || '').match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([\w-]{6,})/);
    return match ? match[1] : null;
}

function Input({ label, error, hint, className = 'col-md-6', ...props }) {
    return (
        <div className={className}>
            {label && <label className="form-label">{label}</label>}
            <input className={`form-control${error ? ' is-invalid' : ''}`} {...props} />
            {hint && <div className="form-text">{hint}</div>}
            {error && <div className="invalid-feedback">{error}</div>}
        </div>
    );
}

function YoutubeThumb({ url }) {
    const id = youtubeId(url);

    if (!id) {
        return null;
    }

    return (
        <img
            src={`https://i.ytimg.com/vi/${id}/mqdefault.jpg`}
            alt=""
            className="rounded mt-2"
            style={{ width: 160, aspectRatio: '16 / 9', objectFit: 'cover' }}
        />
    );
}

/**
 * Where the saved page stands for visitors. Based on what is saved, not on the
 * unsaved form, so the colour never claims a change that has not happened yet.
 */
function landingStatus(course, landing) {
    if (!landing.exists) {
        return { key: 'new', color: '#64748b', bg: '#f1f5f9', icon: 'ri-draft-line', title: 'لم تُحفظ بعد', text: 'احفظ الصفحة أولاً لتتمكن من معاينتها.' };
    }
    if (!landing.is_published) {
        return { key: 'draft', color: '#64748b', bg: '#f1f5f9', icon: 'ri-eye-off-line', title: 'غير منشورة (مسودة)', text: 'لا يراها الزوار — يمكنك معاينتها فقط.' };
    }
    if (!course.is_published) {
        return { key: 'blocked', color: '#b45309', bg: '#fff7e6', icon: 'ri-error-warning-line', title: 'منشورة لكن الدورة غير منشورة', text: 'لن يراها الزوار حتى تُنشر الدورة نفسها.' };
    }
    return { key: 'live', color: '#047857', bg: '#e7f8f1', icon: 'ri-checkbox-circle-fill', title: 'منشورة — يراها الزوار الآن', text: 'الرابط جاهز لاستخدامه في الإعلانات.' };
}

function OpenPageButton({ course, status, className = '' }) {
    if (status.key === 'new') {
        return (
            <button type="button" className={`admin-btn admin-btn-ghost ${className}`} disabled title="احفظ الصفحة أولاً">
                <i className="ri-external-link-line" />
                فتح الصفحة
            </button>
        );
    }

    const live = status.key === 'live';

    return (
        <a
            href={live ? course.landing_url : course.preview_url}
            target="_blank"
            rel="noreferrer"
            className={`admin-btn ${className}`}
            style={live ? { background: '#059669', borderColor: '#059669', color: '#fff' } : undefined}
        >
            <i className={live ? 'ri-external-link-line' : 'ri-eye-line'} />
            {live ? 'فتح الصفحة' : 'معاينة الصفحة'}
        </a>
    );
}

export default function Landing({ course, landing, platforms, flash }) {
    const [copied, setCopied] = useState(false);
    const { data, setData, put, processing, errors, isDirty } = useForm({
        is_published: Boolean(landing.is_published),
        tagline: landing.tagline || '',
        description: landing.description || '',
        videos: landing.videos,
        goals: landing.goals,
        features: landing.features,
        testimonials: landing.testimonials,
        faqs: landing.faqs,
        social_links: landing.social_links,
        show_price: landing.show_price !== false,
        cta_label: landing.cta_label || '',
        cta_url: landing.cta_url || '',
        meta_title: landing.meta_title || '',
        meta_description: landing.meta_description || '',
        allow_indexing: Boolean(landing.allow_indexing),
    });

    const status = landingStatus(course, landing);
    const publishChanged = data.is_published !== Boolean(landing.is_published);

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.courses.landing.update', course.id), { preserveScroll: true });
    };

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(course.landing_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        } catch {
            window.prompt('انسخ الرابط:', course.landing_url);
        }
    };

    const field = (key, extra = {}) => ({
        value: data[key] ?? '',
        error: errors[key],
        onChange: (e) => setData(key, e.target.value),
        ...extra,
    });

    return (
        <AdminUiPage
            title="صفحة الهبوط"
            subtitle={course.title}
            eyebrow="الدورات"
            eyebrowIcon="ri-megaphone-line"
            flash={flash}
            chips={[{ label: 'الحالة', value: status.title, icon: status.icon }]}
            actions={
                <>
                    <OpenPageButton course={course} status={status} className="admin-btn-primary" />
                    <Link href={route('admin.courses.edit', course.id)} className="admin-btn admin-btn-ghost">
                        <i className="ri-arrow-right-line" />
                        بيانات الدورة
                    </Link>
                </>
            }
        >
            <form onSubmit={submit}>
                <div className="row g-4">
                    <div className="col-xl-8">
                        <AdminPanel title="الواجهة والزر" icon="ri-layout-top-line">
                            <div className="row g-3">
                                <Input
                                    label="العبارة التسويقية تحت العنوان"
                                    className="col-12"
                                    hint={course.subtitle ? `اتركها فارغة لاستخدام عنوان الدورة الفرعي: «${course.subtitle}»` : null}
                                    maxLength={300}
                                    {...field('tagline')}
                                />
                                <Input
                                    label="نص الزر"
                                    placeholder="سجّل في الدورة الآن"
                                    className="col-md-5"
                                    {...field('cta_label')}
                                />
                                <Input
                                    label="رابط الزر"
                                    placeholder="فارغ = صندوق التسجيل في صفحة الدورة"
                                    hint="رابط يبدأ بـ https:// أو / — مثلاً واتساب: https://wa.me/9665..."
                                    dir="ltr"
                                    className="col-md-7"
                                    {...field('cta_url')}
                                />
                                <div className="col-12">
                                    <label className="form-check mb-0">
                                        <input
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={data.show_price}
                                            onChange={(e) => setData('show_price', e.target.checked)}
                                        />
                                        <span className="form-check-label">إظهار السعر ({course.price})</span>
                                    </label>
                                </div>
                            </div>
                        </AdminPanel>

                        <AdminPanel title="وصف الدورة" icon="ri-file-text-line" className="mt-4">
                            <TinyMceEditor
                                value={data.description}
                                onChange={(value) => setData('description', value)}
                                height={380}
                            />
                            {errors.description && <div className="invalid-feedback d-block">{errors.description}</div>}
                        </AdminPanel>

                        <AdminPanel title="فيديوهات يوتيوب" icon="ri-youtube-line" className="mt-4">
                            <RepeaterField
                                items={data.videos}
                                onChange={(videos) => setData('videos', videos)}
                                newItem={() => ({ title: '', url: '' })}
                                addLabel="إضافة فيديو"
                                max={12}
                                renderRow={(video, update, index) => (
                                    <div className="row g-2">
                                        <Input
                                            label="رابط يوتيوب"
                                            dir="ltr"
                                            placeholder="https://www.youtube.com/watch?v=..."
                                            className="col-md-7"
                                            value={video.url}
                                            error={errors[`videos.${index}.url`]}
                                            onChange={(e) => update('url', e.target.value)}
                                        />
                                        <Input
                                            label="العنوان (اختياري)"
                                            className="col-md-5"
                                            value={video.title || ''}
                                            error={errors[`videos.${index}.title`]}
                                            onChange={(e) => update('title', e.target.value)}
                                        />
                                        <div className="col-12">
                                            <YoutubeThumb url={video.url} />
                                        </div>
                                    </div>
                                )}
                            />
                        </AdminPanel>

                        <AdminPanel title="أهداف الدورة (ماذا ستتعلم)" icon="ri-focus-3-line" className="mt-4">
                            <ListEditor
                                items={data.goals}
                                onChange={(goals) => setData('goals', goals)}
                                placeholder="مثال: تحريك الشعارات باحتراف"
                                errors={errors}
                                field="goals"
                            />
                        </AdminPanel>

                        <AdminPanel title="مميزات الدورة" icon="ri-star-smile-line" className="mt-4">
                            <RepeaterField
                                items={data.features}
                                onChange={(features) => setData('features', features)}
                                newItem={() => ({ title: '', text: '' })}
                                addLabel="إضافة ميزة"
                                max={12}
                                renderRow={(feature, update, index) => (
                                    <div className="row g-2">
                                        <Input
                                            label="الميزة"
                                            className="col-md-5"
                                            value={feature.title}
                                            error={errors[`features.${index}.title`]}
                                            onChange={(e) => update('title', e.target.value)}
                                        />
                                        <Input
                                            label="شرح مختصر (اختياري)"
                                            className="col-md-7"
                                            value={feature.text || ''}
                                            error={errors[`features.${index}.text`]}
                                            onChange={(e) => update('text', e.target.value)}
                                        />
                                    </div>
                                )}
                            />
                        </AdminPanel>

                        <AdminPanel title="آراء المتدربين" icon="ri-chat-quote-line" className="mt-4">
                            <RepeaterField
                                items={data.testimonials}
                                onChange={(testimonials) => setData('testimonials', testimonials)}
                                newItem={() => ({ name: '', role: '', text: '', video_url: '' })}
                                addLabel="إضافة رأي"
                                renderRow={(item, update, index) => (
                                    <div className="row g-2">
                                        <Input
                                            label="الاسم"
                                            className="col-md-6"
                                            value={item.name}
                                            error={errors[`testimonials.${index}.name`]}
                                            onChange={(e) => update('name', e.target.value)}
                                        />
                                        <Input
                                            label="الصفة (اختياري)"
                                            className="col-md-6"
                                            value={item.role || ''}
                                            error={errors[`testimonials.${index}.role`]}
                                            onChange={(e) => update('role', e.target.value)}
                                        />
                                        <div className="col-12">
                                            <label className="form-label">نص الرأي</label>
                                            <textarea
                                                className={`form-control${errors[`testimonials.${index}.text`] ? ' is-invalid' : ''}`}
                                                rows={2}
                                                value={item.text || ''}
                                                onChange={(e) => update('text', e.target.value)}
                                            />
                                            {errors[`testimonials.${index}.text`] && (
                                                <div className="invalid-feedback">{errors[`testimonials.${index}.text`]}</div>
                                            )}
                                        </div>
                                        <Input
                                            label="أو / و رابط فيديو يوتيوب للرأي"
                                            dir="ltr"
                                            className="col-12"
                                            value={item.video_url || ''}
                                            error={errors[`testimonials.${index}.video_url`]}
                                            onChange={(e) => update('video_url', e.target.value)}
                                        />
                                        <div className="col-12">
                                            <YoutubeThumb url={item.video_url} />
                                        </div>
                                    </div>
                                )}
                            />
                        </AdminPanel>

                        <AdminPanel title="الأسئلة الشائعة" icon="ri-question-answer-line" className="mt-4">
                            <RepeaterField
                                items={data.faqs}
                                onChange={(faqs) => setData('faqs', faqs)}
                                newItem={() => ({ question: '', answer: '' })}
                                addLabel="إضافة سؤال"
                                renderRow={(faq, update, index) => (
                                    <div className="row g-2">
                                        <Input
                                            label="السؤال"
                                            className="col-12"
                                            value={faq.question}
                                            error={errors[`faqs.${index}.question`]}
                                            onChange={(e) => update('question', e.target.value)}
                                        />
                                        <div className="col-12">
                                            <label className="form-label">الجواب</label>
                                            <textarea
                                                className={`form-control${errors[`faqs.${index}.answer`] ? ' is-invalid' : ''}`}
                                                rows={3}
                                                value={faq.answer}
                                                onChange={(e) => update('answer', e.target.value)}
                                            />
                                            {errors[`faqs.${index}.answer`] && (
                                                <div className="invalid-feedback">{errors[`faqs.${index}.answer`]}</div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            />
                        </AdminPanel>

                        <AdminPanel title="وسائل التواصل" icon="ri-share-line" className="mt-4">
                            <div className="small text-muted mb-3">
                                <i className="ri-whatsapp-line text-success" /> أول رابط واتساب يظهر كزر كبير متحرك أسفل الصفحة.
                            </div>
                            <SocialLinksField
                                links={data.social_links}
                                platforms={platforms}
                                errors={errors}
                                max={10}
                                onChange={(links) => setData('social_links', links)}
                            />
                        </AdminPanel>
                    </div>

                    <div className="col-xl-4">
                        <AdminPanel title="النشر والرابط" icon="ri-links-line">
                            <div
                                className="d-flex align-items-start gap-2 rounded-3 p-3 mb-3"
                                style={{ background: status.bg, color: status.color, border: `1px solid ${status.color}33` }}
                            >
                                <i className={status.icon} style={{ fontSize: 26, lineHeight: 1 }} />
                                <div>
                                    <div className="fw-bold">{status.title}</div>
                                    <div className="small">{status.text}</div>
                                </div>
                            </div>

                            <label className="form-label">حالة النشر</label>
                            <div className="d-flex gap-2 mb-2" role="radiogroup" aria-label="حالة النشر">
                                {[
                                    { value: true, label: 'منشورة', icon: 'ri-global-line', color: '#059669' },
                                    { value: false, label: 'مسودة', icon: 'ri-eye-off-line', color: '#64748b' },
                                ].map((option) => {
                                    const active = data.is_published === option.value;
                                    return (
                                        <button
                                            key={option.label}
                                            type="button"
                                            role="radio"
                                            aria-checked={active}
                                            className="btn flex-fill fw-semibold"
                                            style={{
                                                background: active ? option.color : '#fff',
                                                color: active ? '#fff' : option.color,
                                                border: `2px solid ${option.color}`,
                                            }}
                                            onClick={() => setData('is_published', option.value)}
                                        >
                                            <i className={`${option.icon} ms-1`} />
                                            {option.label}
                                        </button>
                                    );
                                })}
                            </div>
                            {publishChanged && (
                                <div className="small fw-semibold mb-3" style={{ color: '#b45309' }}>
                                    <i className="ri-information-line" /> يُطبَّق التغيير بعد الضغط على «حفظ».
                                </div>
                            )}

                            <label className="form-label">رابط الإعلان</label>
                            <div className="input-group">
                                <input className="form-control" dir="ltr" readOnly value={course.landing_url} />
                                <button type="button" className="btn btn-outline-primary" onClick={copyLink}>
                                    <i className={copied ? 'ri-check-line' : 'ri-file-copy-line'} />
                                </button>
                            </div>
                            <div className="form-text mb-3">
                                أضف ‎?utm_source=…‎ لتتبّع كل حملة إعلانية.
                            </div>
                            <OpenPageButton course={course} status={status} className="admin-btn-ghost w-100 justify-content-center" />
                        </AdminPanel>

                        <AdminPanel title="مأخوذ من بيانات الدورة" icon="ri-links-line" className="mt-4">
                            {course.thumbnail_url && (
                                <img
                                    src={course.thumbnail_url}
                                    alt=""
                                    className="w-100 rounded mb-2"
                                    style={{ aspectRatio: '16 / 9', objectFit: 'cover' }}
                                />
                            )}
                            <div className="fw-bold">{course.title}</div>
                            <div className="small text-muted mb-2">{course.price}</div>
                            <p className="small text-muted mb-0">
                                العنوان والصورة والسعر وعدد الدروس والمدة والمدرّب تُعرض كما هي في الدورة —
                                عدّلها من{' '}
                                <Link href={route('admin.courses.edit', course.id)}>بيانات الدورة</Link> لتتغير في الصفحتين.
                            </p>
                        </AdminPanel>

                        <AdminPanel title="محركات البحث والمشاركة" icon="ri-search-eye-line" className="mt-4">
                            <div className="row g-3">
                                <Input label="عنوان الصفحة" className="col-12" placeholder={course.title} maxLength={120} {...field('meta_title')} />
                                <div className="col-12">
                                    <label className="form-label">الوصف عند المشاركة</label>
                                    <textarea
                                        className={`form-control${errors.meta_description ? ' is-invalid' : ''}`}
                                        rows={3}
                                        maxLength={320}
                                        value={data.meta_description}
                                        onChange={(e) => setData('meta_description', e.target.value)}
                                    />
                                </div>
                                <div className="col-12">
                                    <label className="form-check mb-0">
                                        <input
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={data.allow_indexing}
                                            onChange={(e) => setData('allow_indexing', e.target.checked)}
                                        />
                                        <span className="form-check-label">السماح بأرشفتها في محركات البحث</span>
                                    </label>
                                    <div className="form-text">صفحات الإعلانات تُستثنى عادة كي لا تنافس صفحة الدورة.</div>
                                </div>
                            </div>
                        </AdminPanel>

                        <div className="position-sticky mt-4" style={{ bottom: 16 }}>
                            <button type="submit" className="admin-btn admin-btn-primary w-100" disabled={processing}>
                                <i className="ri-save-line" />
                                {processing ? 'جارٍ الحفظ...' : isDirty ? 'حفظ التغييرات' : 'حفظ'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </AdminUiPage>
    );
}
