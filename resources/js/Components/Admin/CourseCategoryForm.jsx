import { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { plainText } from '@/Components/Admin/courseFormat';
import IconPicker from '@/Components/Admin/IconPicker';
import MediaField from '@/Components/Admin/MediaField';
import SearchPreview from '@/Components/Admin/SearchPreview';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

export const COURSE_CATEGORY_ICONS = [
    { value: '', label: 'بدون أيقونة' },
    { value: 'ri-book-open-line', label: 'كتاب' },
    { value: 'ri-graduation-cap-line', label: 'قبعة تخرج' },
    { value: 'ri-code-s-slash-line', label: 'برمجة' },
    { value: 'ri-palette-line', label: 'تصميم' },
    { value: 'ri-line-chart-line', label: 'أعمال وتسويق' },
    { value: 'ri-translate-2', label: 'لغات' },
    { value: 'ri-calculator-line', label: 'رياضيات' },
    { value: 'ri-flask-line', label: 'علوم' },
    { value: 'ri-camera-line', label: 'تصوير' },
    { value: 'ri-movie-2-line', label: 'فيديو وموشن' },
    { value: 'ri-music-2-line', label: 'موسيقى' },
    { value: 'ri-heart-pulse-line', label: 'صحة ولياقة' },
    { value: 'ri-brain-line', label: 'تطوير ذاتي' },
    { value: 'ri-computer-line', label: 'تقنية' },
    { value: 'ri-robot-2-line', label: 'ذكاء اصطناعي' },
    { value: 'ri-shield-check-line', label: 'أمن معلومات' },
    { value: 'ri-quill-pen-line', label: 'كتابة' },
];

// The Motion Academy palette (sampled from the logo), so categories stay on-brand.
const COLOR_SWATCHES = [
    '#02ad8f',
    '#06b59c',
    '#45bec4',
    '#33a4da',
    '#1a83c1',
    '#0f2a4a',
    '#018a72',
    '#8fd9e0',
];

/**
 * Create/edit form shared by the course category Create and Edit pages.
 * Pass `category` to edit; omit it to create.
 */
export default function CourseCategoryForm({ category = null, roots = [], defaultParentId = null }) {
    const { errors: pageErrors, site } = usePage().props;
    const isEdit = Boolean(category);

    const { data, setData, post, processing, errors, transform } = useForm({
        name: category?.name || '',
        slug: category?.slug || '',
        description: category?.description || '',
        parent_id: category?.parent_id ?? defaultParentId ?? '',
        icon: category?.icon || '',
        color: category?.color || '#02ad8f',
        image: null,
        image_url: '',
        remove_image: false,
        image_preview: category?.image_url || '',
        meta_title: category?.meta_title || '',
        meta_description: category?.meta_description || '',
        is_indexable: category ? category.is_indexable !== false : true,
        meta_keywords: category?.meta_keywords || '',
        og_image: category?.og_image || '',
        is_active: category ? Boolean(category.is_active) : true,
        is_featured: Boolean(category?.is_featured),
        order: category?.order ?? '',
        ...(isEdit ? { _method: 'put' } : {}),
    });

    const fieldErrors = { ...pageErrors, ...errors };

    const objectUrl = useMemo(
        () => (data.image instanceof File ? URL.createObjectURL(data.image) : null),
        [data.image],
    );
    useEffect(() => () => objectUrl && URL.revokeObjectURL(objectUrl), [objectUrl]);
    const previewImage = objectUrl || data.image_preview;

    const submit = (e) => {
        e.preventDefault();
        // eslint-disable-next-line no-unused-vars
        transform(({ image_preview, ...payload }) => payload);
        post(
            isEdit
                ? route('admin.course-categories.update', category.id)
                : route('admin.course-categories.store'),
            { forceFormData: true, preserveScroll: true },
        );
    };

    const textField = (field, label, props = {}) => (
        <>
            <label className="form-label">{label}</label>
            <input
                type="text"
                className={`form-control${fieldErrors[field] ? ' is-invalid' : ''}`}
                value={data[field]}
                onChange={(e) => setData(field, e.target.value)}
                {...props}
            />
            {fieldErrors[field] && <div className="invalid-feedback">{fieldErrors[field]}</div>}
        </>
    );

    const parentLocked = isEdit && category.has_children;

    return (
        <form onSubmit={submit}>
            <div className="row g-4">
                <div className="col-xl-8">
                    <AdminPanel title="المعلومات الأساسية" icon="ri-information-line">
                        <div className="row g-3">
                            <div className="col-md-6">
                                {textField('name', 'اسم التصنيف *', { autoFocus: !isEdit })}
                            </div>
                            <div className="col-md-6">
                                {textField('slug', 'المسار (Slug)', {
                                    dir: 'ltr',
                                    placeholder: 'يُولَّد تلقائياً من الاسم',
                                })}
                            </div>
                            <div className="col-12">
                                <label className="form-label">الوصف</label>
                                <TinyMceEditor
                                    value={data.description}
                                    onChange={(content) => setData('description', content)}
                                    height={260}
                                />
                                {fieldErrors.description && (
                                    <div className="invalid-feedback d-block">{fieldErrors.description}</div>
                                )}
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="تحسين محركات البحث (SEO)" icon="ri-search-eye-line" className="mt-4">
                        <div className="row g-3">
                            <div className="col-md-6">
                                {textField('meta_title', 'عنوان الميتا', { placeholder: data.name || '' })}
                            </div>
                            <div className="col-md-6">
                                {textField('meta_keywords', 'الكلمات المفتاحية', {
                                    placeholder: 'برمجة, تطوير ويب, دورات',
                                })}
                            </div>
                            <div className="col-12">
                                <label className="form-label">وصف الميتا</label>
                                <textarea
                                    rows={2}
                                    className={`form-control${fieldErrors.meta_description ? ' is-invalid' : ''}`}
                                    value={data.meta_description}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                />
                                {fieldErrors.meta_description && (
                                    <div className="invalid-feedback d-block">
                                        {fieldErrors.meta_description}
                                    </div>
                                )}
                            </div>
                            <div className="col-12">
                                <SearchPreview
                                    title={data.meta_title || `دورات ${data.name || ''}`}
                                    description={data.meta_description || plainText(data.description)}
                                    url={`${window.location.origin}/courses?category=${data.slug || '…'}`}
                                    siteName={site?.site_name}
                                    titleLength={(data.meta_title || `دورات ${data.name || ''}`).length}
                                    descriptionLength={
                                        (data.meta_description || plainText(data.description)).length
                                    }
                                    indexable={data.is_indexable}
                                    onIndexableChange={(value) => setData('is_indexable', value)}
                                />
                            </div>
                            <div className="col-12">
                                <MediaField
                                    label="صورة المشاركة (Open Graph)"
                                    previewUrl={data.og_image || null}
                                    error={fieldErrors.og_image}
                                    allowUpload={false}
                                    onPick={(item) => setData('og_image', item.url)}
                                    onClear={() => setData('og_image', '')}
                                />
                            </div>
                        </div>
                    </AdminPanel>
                </div>

                <div className="col-xl-4">
                    <AdminPanel title="معاينة" icon="ri-eye-line">
                        <div
                            className="course-cat-preview"
                            style={{ '--cat-color': data.color || '#02ad8f' }}
                        >
                            <div className="course-cat-preview__cover">
                                {previewImage ? (
                                    <img src={previewImage} alt="" />
                                ) : (
                                    <i className={data.icon || 'ri-folder-3-line'} />
                                )}
                            </div>
                            <div className="course-cat-preview__body">
                                <span className="course-cat-avatar">
                                    <i className={data.icon || 'ri-folder-3-line'} />
                                </span>
                                <div className="min-w-0">
                                    <strong className="d-block text-truncate">
                                        {data.name || 'اسم التصنيف'}
                                    </strong>
                                    <small className="text-muted">
                                        {roots.find((r) => String(r.id) === String(data.parent_id))?.name ||
                                            'تصنيف رئيسي'}
                                    </small>
                                </div>
                                {data.is_featured && (
                                    <span className="admin-badge admin-badge-success ms-auto">مميز</span>
                                )}
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="النشر والتنظيم" icon="ri-settings-3-line" className="mt-4">
                        <div className="form-check form-switch mb-2">
                            <input
                                id="cat-active"
                                type="checkbox"
                                className="form-check-input"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                            />
                            <label className="form-check-label" htmlFor="cat-active">
                                نشط (ظاهر للطلاب)
                            </label>
                        </div>
                        <div className="form-check form-switch mb-3">
                            <input
                                id="cat-featured"
                                type="checkbox"
                                className="form-check-input"
                                checked={data.is_featured}
                                onChange={(e) => setData('is_featured', e.target.checked)}
                            />
                            <label className="form-check-label" htmlFor="cat-featured">
                                مميز (يظهر في الواجهة الرئيسية)
                            </label>
                        </div>

                        <div className="mb-3">
                            <label className="form-label">التصنيف الأب</label>
                            <select
                                className={`form-select${fieldErrors.parent_id ? ' is-invalid' : ''}`}
                                value={data.parent_id}
                                disabled={parentLocked}
                                onChange={(e) => setData('parent_id', e.target.value)}
                            >
                                <option value="">— تصنيف رئيسي —</option>
                                {roots.map((root) => (
                                    <option key={root.id} value={root.id}>
                                        {root.name}
                                    </option>
                                ))}
                            </select>
                            {parentLocked && (
                                <div className="form-text">
                                    هذا التصنيف يحتوي على تصنيفات فرعية، لذا يبقى تصنيفاً رئيسياً.
                                </div>
                            )}
                            {fieldErrors.parent_id && (
                                <div className="invalid-feedback">{fieldErrors.parent_id}</div>
                            )}
                        </div>

                        <div>
                            <label className="form-label">الترتيب</label>
                            <input
                                type="number"
                                min={0}
                                className={`form-control${fieldErrors.order ? ' is-invalid' : ''}`}
                                value={data.order}
                                placeholder="تلقائي (في آخر القائمة)"
                                onChange={(e) => setData('order', e.target.value)}
                            />
                            <div className="form-text">يمكنك أيضاً إعادة الترتيب بالسحب من صفحة القائمة.</div>
                            {fieldErrors.order && <div className="invalid-feedback">{fieldErrors.order}</div>}
                        </div>
                    </AdminPanel>

                    <AdminPanel title="الهوية البصرية" icon="ri-palette-line" className="mt-4">
                        <MediaField
                            label="صورة الغلاف"
                            previewUrl={previewImage || null}
                            error={fieldErrors.image || fieldErrors.image_url}
                            onPick={(item) =>
                                setData((current) => ({
                                    ...current,
                                    image: null,
                                    image_url: item.url,
                                    image_preview: item.url,
                                    remove_image: false,
                                }))
                            }
                            onFile={(file) =>
                                setData((current) => ({
                                    ...current,
                                    image: file,
                                    image_url: '',
                                    remove_image: false,
                                }))
                            }
                            onClear={() =>
                                setData((current) => ({
                                    ...current,
                                    image: null,
                                    image_url: '',
                                    image_preview: '',
                                    remove_image: true,
                                }))
                            }
                        />

                        <label className="form-label">الأيقونة</label>
                        <IconPicker
                            value={data.icon}
                            options={COURSE_CATEGORY_ICONS}
                            onChange={(value) => setData('icon', value)}
                            error={fieldErrors.icon}
                        />

                        <label className="form-label mt-3">اللون</label>
                        <div className="d-flex align-items-center gap-2 flex-wrap">
                            <input
                                type="color"
                                className="form-control form-control-color"
                                value={data.color || '#02ad8f'}
                                onChange={(e) => setData('color', e.target.value)}
                                title="اختر لوناً"
                            />
                            {COLOR_SWATCHES.map((swatch) => (
                                <button
                                    key={swatch}
                                    type="button"
                                    className={`course-cat-swatch${data.color === swatch ? ' is-active' : ''}`}
                                    style={{ background: swatch }}
                                    onClick={() => setData('color', swatch)}
                                    aria-label={swatch}
                                />
                            ))}
                        </div>
                        {fieldErrors.color && (
                            <div className="invalid-feedback d-block">{fieldErrors.color}</div>
                        )}
                    </AdminPanel>
                </div>

                <div className="col-12 d-flex gap-2">
                    <button type="submit" className="admin-btn admin-btn-primary" disabled={processing}>
                        <i className={processing ? 'ri-loader-4-line' : 'ri-save-line'} />
                        {isEdit ? 'حفظ التعديلات' : 'إنشاء التصنيف'}
                    </button>
                    <Link href={route('admin.course-categories.index')} className="admin-btn admin-btn-ghost">
                        إلغاء
                    </Link>
                </div>
            </div>
        </form>
    );
}
