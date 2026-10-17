import { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { CURRENCIES, formatPrice, LANGUAGES, plainText } from '@/Components/Admin/courseFormat';
import ListEditor from '@/Components/Admin/ListEditor';
import MediaField from '@/Components/Admin/MediaField';
import SearchPreview from '@/Components/Admin/SearchPreview';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import VideoSourceField from '@/Components/Admin/VideoSourceField';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

/**
 * Create/edit form shared by the course Create and Edit pages.
 * Pass `course` to edit; omit it to create.
 */
export default function CourseForm({
    course = null,
    categories = [],
    instructors = [],
    statuses = [],
    levels = [],
    videoSources = [],
    maxVideoUploadMb = null,
    defaultCategoryId = null,
}) {
    const { errors: pageErrors, site } = usePage().props;
    const isEdit = Boolean(course);
    const introIsUpload = course?.intro_video_source === 'upload';

    const { data, setData, post, processing, errors, progress, transform } = useForm({
        title: course?.title || '',
        slug: course?.slug || '',
        subtitle: course?.subtitle || '',
        short_description: course?.short_description || '',
        description: course?.description || '',
        course_category_id: course?.course_category_id ?? defaultCategoryId ?? '',
        instructor_id: course?.instructor_id ?? '',
        level: course?.level || 'all',
        language: course?.language || 'ar',
        status: course?.status || 'draft',
        published_at: course?.published_at_input || '',
        is_featured: Boolean(course?.is_featured),
        is_free: Boolean(course?.is_free),
        price: course ? course.price : '',
        sale_price: course?.sale_price ?? '',
        sale_ends_at: course?.sale_ends_at || '',
        currency: course?.currency || 'USD',
        thumbnail: null,
        thumbnail_url: '',
        remove_thumbnail: false,
        thumbnail_preview: course?.thumbnail_url || '',
        og_image: course?.og_image || '',
        is_indexable: course ? course.is_indexable !== false : true,
        intro_video_source: course?.intro_video_source || '',
        intro_video: introIsUpload ? '' : course?.intro_video || '',
        intro_video_file: null,
        learning_outcomes: course?.learning_outcomes || [],
        requirements: course?.requirements || [],
        target_audience: course?.target_audience || [],
        has_certificate: Boolean(course?.has_certificate),
        max_students: course?.max_students ?? '',
        meta_title: course?.meta_title || '',
        meta_description: course?.meta_description || '',
        meta_keywords: course?.meta_keywords || '',
        ...(isEdit ? { _method: 'put' } : {}),
    });

    const fieldErrors = { ...pageErrors, ...errors };
    const shortDescriptionLength = plainText(data.short_description).length;

    const thumbObjectUrl = useMemo(
        () => (data.thumbnail instanceof File ? URL.createObjectURL(data.thumbnail) : null),
        [data.thumbnail],
    );
    useEffect(() => () => thumbObjectUrl && URL.revokeObjectURL(thumbObjectUrl), [thumbObjectUrl]);
    const thumbnailPreview = thumbObjectUrl || data.thumbnail_preview;

    const discount =
        !data.is_free &&
        Number(data.price) > 0 &&
        data.sale_price !== '' &&
        Number(data.sale_price) < Number(data.price)
            ? Math.round((1 - Number(data.sale_price) / Number(data.price)) * 100)
            : null;

    const submit = (e) => {
        e.preventDefault();
        // eslint-disable-next-line no-unused-vars
        transform(({ thumbnail_preview, ...payload }) => payload);
        post(isEdit ? route('admin.courses.update', course.id) : route('admin.courses.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const input = (field, label, props = {}) => (
        <>
            <label className="form-label">{label}</label>
            <input
                type="text"
                className={`form-control${fieldErrors[field] ? ' is-invalid' : ''}`}
                value={data[field] ?? ''}
                onChange={(e) => setData(field, e.target.value)}
                {...props}
            />
            {fieldErrors[field] && <div className="invalid-feedback">{fieldErrors[field]}</div>}
        </>
    );

    const switchField = (field, label, hint = null) => (
        <div className="form-check form-switch mb-2">
            <input
                id={`course-${field}`}
                type="checkbox"
                className="form-check-input"
                checked={data[field]}
                onChange={(e) => setData(field, e.target.checked)}
            />
            <label className="form-check-label" htmlFor={`course-${field}`}>
                {label}
                {hint && <small className="d-block text-muted">{hint}</small>}
            </label>
        </div>
    );

    const firstError = Object.keys(fieldErrors).length > 0;

    return (
        <form onSubmit={submit}>
            {firstError && (
                <div className="admin-alert admin-alert-error mb-3">
                    <i className="ri-error-warning-line" /> يرجى مراجعة الحقول المظللة بالأحمر.
                </div>
            )}

            <div className="row g-4">
                <div className="col-xl-8">
                    <AdminPanel title="المعلومات الأساسية" icon="ri-information-line">
                        <div className="row g-3">
                            <div className="col-12">
                                {input('title', 'عنوان الدورة *', { autoFocus: !isEdit })}
                            </div>
                            <div className="col-md-6">{input('subtitle', 'العنوان الفرعي')}</div>
                            <div className="col-md-6">
                                {input('slug', 'المسار (Slug)', {
                                    dir: 'ltr',
                                    placeholder: 'يُولَّد تلقائياً من العنوان',
                                })}
                            </div>
                            <div className="col-12">
                                <label className="form-label">الوصف المختصر</label>
                                <TinyMceEditor
                                    value={data.short_description}
                                    onChange={(content) => setData('short_description', content)}
                                    height={200}
                                />
                                <div
                                    className={`form-text${shortDescriptionLength > 500 ? ' text-danger' : ''}`}
                                >
                                    جملة أو جملتان تظهران في بطاقة الدورة — {shortDescriptionLength}/500 حرف
                                </div>
                                {fieldErrors.short_description && (
                                    <div className="invalid-feedback d-block">
                                        {fieldErrors.short_description}
                                    </div>
                                )}
                            </div>
                            <div className="col-12">
                                <label className="form-label">الوصف التفصيلي</label>
                                <TinyMceEditor
                                    value={data.description}
                                    onChange={(content) => setData('description', content)}
                                    height={380}
                                />
                                {fieldErrors.description && (
                                    <div className="invalid-feedback d-block">{fieldErrors.description}</div>
                                )}
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="تفاصيل الدورة" icon="ri-list-check-2" className="mt-4">
                        <ListEditor
                            label="ماذا سيتعلم الطالب؟"
                            icon="ri-checkbox-circle-line text-success"
                            field="learning_outcomes"
                            items={data.learning_outcomes}
                            onChange={(items) => setData('learning_outcomes', items)}
                            placeholder="مثال: تحريك النصوص باحتراف في After Effects"
                            errors={fieldErrors}
                        />
                        <ListEditor
                            label="المتطلبات المسبقة"
                            icon="ri-error-warning-line text-warning"
                            field="requirements"
                            items={data.requirements}
                            onChange={(items) => setData('requirements', items)}
                            placeholder="مثال: جهاز كمبيوتر بذاكرة 8GB"
                            errors={fieldErrors}
                        />
                        <ListEditor
                            label="لمن هذه الدورة؟"
                            icon="ri-user-star-line text-primary"
                            field="target_audience"
                            items={data.target_audience}
                            onChange={(items) => setData('target_audience', items)}
                            placeholder="مثال: المصممون المبتدئون"
                            errors={fieldErrors}
                        />
                    </AdminPanel>

                    <AdminPanel title="الفيديو التعريفي" icon="ri-movie-2-line" className="mt-4">
                        <VideoSourceField
                            allowNone
                            sources={videoSources}
                            source={data.intro_video_source}
                            onSourceChange={(value) => setData('intro_video_source', value)}
                            url={data.intro_video}
                            onUrlChange={(value) => setData('intro_video', value)}
                            file={data.intro_video_file}
                            onFileChange={(file) => setData('intro_video_file', file)}
                            existingUrl={introIsUpload ? course.intro_video_url : null}
                            existingName={introIsUpload ? course.intro_video?.split('/').pop() : null}
                            maxMb={maxVideoUploadMb}
                            errors={{
                                source: fieldErrors.intro_video_source,
                                url: fieldErrors.intro_video,
                                file: fieldErrors.intro_video_file,
                            }}
                        />
                    </AdminPanel>

                    <AdminPanel title="تحسين محركات البحث (SEO)" icon="ri-search-eye-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-md-6">
                                {input('meta_title', 'عنوان الميتا', { placeholder: data.title })}
                            </div>
                            <div className="col-md-6">{input('meta_keywords', 'الكلمات المفتاحية')}</div>
                            <div className="col-12">
                                <label className="form-label">وصف الميتا</label>
                                <textarea
                                    rows={2}
                                    className="form-control"
                                    value={data.meta_description}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                    placeholder={plainText(data.short_description)}
                                />
                            </div>
                            <div className="col-12">
                                <SearchPreview
                                    title={data.meta_title || data.title}
                                    description={data.meta_description || plainText(data.short_description)}
                                    url={`${window.location.origin}/courses/${data.slug || '…'}`}
                                    siteName={site?.site_name}
                                    titleLength={(data.meta_title || data.title).length}
                                    descriptionLength={
                                        (data.meta_description || plainText(data.short_description)).length
                                    }
                                    indexable={data.is_indexable}
                                    onIndexableChange={(value) => setData('is_indexable', value)}
                                />
                            </div>
                            <div className="col-12">
                                <MediaField
                                    label="صورة المشاركة (Open Graph)"
                                    previewUrl={data.og_image || null}
                                    allowUpload={false}
                                    error={fieldErrors.og_image}
                                    onPick={(item) => setData('og_image', item.url)}
                                    onClear={() => setData('og_image', '')}
                                />
                                <div className="form-text">
                                    1200×630 بكسل مثالية. بدونها تُستخدم صورة الدورة.
                                </div>
                            </div>
                        </div>
                    </AdminPanel>
                </div>

                <div className="col-xl-4">
                    <AdminPanel title="النشر" icon="ri-send-plane-line">
                        <label className="form-label">الحالة</label>
                        <div className="course-status-picker mb-2">
                            {statuses.map((status) => (
                                <button
                                    key={status.value}
                                    type="button"
                                    className={`course-status-option is-${status.value}${data.status === status.value ? ' is-active' : ''}`}
                                    onClick={() => setData('status', status.value)}
                                >
                                    {status.label}
                                </button>
                            ))}
                        </div>
                        {fieldErrors.status && (
                            <div className="invalid-feedback d-block mb-2">{fieldErrors.status}</div>
                        )}
                        {!isEdit && (
                            <div className="form-text mb-2">
                                تُحفظ الدورة الجديدة كمسودة، ثم تنشرها بعد إضافة الدروس.
                            </div>
                        )}
                        <div className="mb-3">
                            {input('published_at', 'تاريخ النشر', { type: 'datetime-local', dir: 'ltr' })}
                            <div className="form-text">اتركه فارغاً ليُضبط تلقائياً عند النشر.</div>
                        </div>
                        {switchField('is_featured', 'دورة مميزة', 'تظهر في الواجهة الرئيسية')}
                    </AdminPanel>

                    <AdminPanel title="التنظيم" icon="ri-node-tree" className="mt-4">
                        <div className="mb-3">
                            <label className="form-label">التصنيف *</label>
                            <select
                                className={`form-select${fieldErrors.course_category_id ? ' is-invalid' : ''}`}
                                value={data.course_category_id}
                                onChange={(e) => setData('course_category_id', e.target.value)}
                            >
                                <option value="">— اختر التصنيف —</option>
                                {categories.map((root) => (
                                    <optgroup key={root.id} label={root.name}>
                                        <option value={root.id}>{root.name}</option>
                                        {root.children.map((child) => (
                                            <option key={child.id} value={child.id}>
                                                — {child.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                            {fieldErrors.course_category_id && (
                                <div className="invalid-feedback">{fieldErrors.course_category_id}</div>
                            )}
                        </div>
                        <div className="mb-3">
                            <label className="form-label">المدرّس</label>
                            <select
                                className={`form-select${fieldErrors.instructor_id ? ' is-invalid' : ''}`}
                                value={data.instructor_id}
                                onChange={(e) => setData('instructor_id', e.target.value)}
                            >
                                <option value="">— بدون مدرّس —</option>
                                {instructors.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name} ({user.email})
                                    </option>
                                ))}
                            </select>
                            {instructors.length === 0 && (
                                <div className="form-text">لا يوجد مستخدمون بدور مدرّس بعد.</div>
                            )}
                            {fieldErrors.instructor_id && (
                                <div className="invalid-feedback">{fieldErrors.instructor_id}</div>
                            )}
                        </div>
                        <div className="row g-2">
                            <div className="col-6">
                                <label className="form-label">المستوى</label>
                                <select
                                    className="form-select"
                                    value={data.level}
                                    onChange={(e) => setData('level', e.target.value)}
                                >
                                    {levels.map((level) => (
                                        <option key={level.value} value={level.value}>
                                            {level.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="col-6">
                                <label className="form-label">اللغة</label>
                                <select
                                    className="form-select"
                                    value={data.language}
                                    onChange={(e) => setData('language', e.target.value)}
                                >
                                    {LANGUAGES.map((language) => (
                                        <option key={language.value} value={language.value}>
                                            {language.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="التسعير" icon="ri-price-tag-3-line" className="mt-4">
                        {switchField('is_free', 'دورة مجانية')}
                        {!data.is_free && (
                            <div className="row g-2">
                                <div className="col-7">
                                    {input('price', 'السعر *', {
                                        type: 'number',
                                        min: 0,
                                        step: '0.01',
                                        dir: 'ltr',
                                    })}
                                </div>
                                <div className="col-5">
                                    <label className="form-label">العملة</label>
                                    <select
                                        className="form-select"
                                        value={data.currency}
                                        onChange={(e) => setData('currency', e.target.value)}
                                    >
                                        {CURRENCIES.map((currency) => (
                                            <option key={currency.value} value={currency.value}>
                                                {currency.value}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-12">
                                    {input('sale_price', 'سعر الخصم', {
                                        type: 'number',
                                        min: 0,
                                        step: '0.01',
                                        dir: 'ltr',
                                        placeholder: 'اختياري',
                                    })}
                                </div>
                                {data.sale_price !== '' && (
                                    <div className="col-12">
                                        {input('sale_ends_at', 'ينتهي الخصم في', {
                                            type: 'datetime-local',
                                            dir: 'ltr',
                                        })}
                                        <div className="form-text">اتركه فارغاً لخصم دائم.</div>
                                    </div>
                                )}
                                {discount !== null && (
                                    <div className="col-12">
                                        <div className="course-price-summary">
                                            <span>السعر للطالب</span>
                                            <strong>{formatPrice(data.sale_price, data.currency)}</strong>
                                            <del>{formatPrice(data.price, data.currency)}</del>
                                            <span className="admin-badge admin-badge-danger">
                                                خصم {discount}%
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </AdminPanel>

                    <AdminPanel title="صورة الدورة" icon="ri-image-line" className="mt-4">
                        {thumbnailPreview && (
                            <div className="course-thumb-preview mb-2">
                                <img src={thumbnailPreview} alt="" />
                                <button
                                    type="button"
                                    className="admin-action-btn admin-action-delete course-thumb-remove"
                                    title="إزالة الصورة"
                                    onClick={() =>
                                        setData((current) => ({
                                            ...current,
                                            thumbnail: null,
                                            thumbnail_url: '',
                                            thumbnail_preview: '',
                                            remove_thumbnail: true,
                                        }))
                                    }
                                >
                                    <i className="ri-delete-bin-line" />
                                </button>
                            </div>
                        )}
                        <MediaField
                            label={null}
                            previewUrl={null}
                            error={fieldErrors.thumbnail || fieldErrors.thumbnail_url}
                            onPick={(item) =>
                                setData((current) => ({
                                    ...current,
                                    thumbnail: null,
                                    thumbnail_url: item.url,
                                    thumbnail_preview: item.url,
                                    remove_thumbnail: false,
                                }))
                            }
                            onFile={(file) =>
                                setData((current) => ({
                                    ...current,
                                    thumbnail: file,
                                    thumbnail_url: '',
                                    remove_thumbnail: false,
                                }))
                            }
                        />
                        <div className="form-text">المقاس المستحسن 1280×720 (نسبة 16:9).</div>
                    </AdminPanel>

                    <AdminPanel title="خيارات إضافية" icon="ri-settings-3-line" className="mt-4">
                        {switchField(
                            'has_certificate',
                            'شهادة إتمام',
                            'يحصل الطالب على شهادة عند إنهاء الدورة',
                        )}
                        <div className="mt-3">
                            {input('max_students', 'الحد الأقصى للطلاب', {
                                type: 'number',
                                min: 1,
                                placeholder: 'غير محدود',
                            })}
                        </div>
                    </AdminPanel>
                </div>

                <div className="col-12">
                    {progress && (
                        <div className="progress mb-2" style={{ height: 8 }}>
                            <div
                                className="progress-bar bg-success"
                                style={{ width: `${progress.percentage}%` }}
                            />
                        </div>
                    )}
                    <div className="d-flex gap-2 flex-wrap">
                        <button type="submit" className="admin-btn admin-btn-primary" disabled={processing}>
                            <i className={processing ? 'ri-loader-4-line' : 'ri-save-line'} />
                            {isEdit ? 'حفظ التعديلات' : 'إنشاء الدورة والانتقال للمنهج'}
                        </button>
                        {isEdit && (
                            <Link
                                href={route('admin.courses.curriculum', course.id)}
                                className="admin-btn admin-btn-ghost"
                            >
                                <i className="ri-list-check-3" />
                                إدارة المنهج
                            </Link>
                        )}
                        <Link href={route('admin.courses.index')} className="admin-btn admin-btn-ghost">
                            إلغاء
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    );
}
