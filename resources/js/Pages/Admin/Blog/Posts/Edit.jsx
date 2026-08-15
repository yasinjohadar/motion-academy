import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import BlogSchemaFields from '@/Components/Admin/BlogSchemaFields';
import BlogSeoFields from '@/Components/Admin/BlogSeoFields';
import BlogSocialSeoFields from '@/Components/Admin/BlogSocialSeoFields';
import MediaField from '@/Components/Admin/MediaField';
import TinyMceEditor from '@/Components/Admin/TinyMceEditor';
import useCan from '@/hooks/useCan';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Edit({ post, categories = [], tags = [], flash }) {
    const { can } = useCan();
    const { errors: pageErrors } = usePage().props;
    const [activeTab, setActiveTab] = useState('general');
    const [deletingImage, setDeletingImage] = useState(false);

    const { data, setData, post: submitPost, processing, errors } = useForm({
        title: post.title || '',
        slug: post.slug || '',
        excerpt: post.excerpt || '',
        content: post.content || '',
        blog_category_id: post.blog_category_id || '',
        tag_ids: post.tag_ids || [],
        status: post.status || 'draft',
        featured_image: null,
        featured_image_url: '',
        featured_image_preview: post.featured_image_url || '',
        featured_image_alt: post.featured_image_alt || '',
        is_featured: Boolean(post.is_featured),
        language: post.language || 'ar',
        published_at: post.published_at || '',
        meta_title: post.meta_title || '',
        meta_description: post.meta_description || '',
        meta_keywords: post.meta_keywords || '',
        canonical_url: post.canonical_url || '',
        focus_keyword: post.focus_keyword || '',
        focus_keyword_synonyms: post.focus_keyword_synonyms || '',
        robots_meta: post.robots_meta || 'index,follow',
        is_indexable: Boolean(post.is_indexable ?? true),
        is_followable: Boolean(post.is_followable ?? true),
        og_title: post.og_title || '',
        og_description: post.og_description || '',
        og_image: post.og_image || '',
        og_type: post.og_type || 'article',
        og_locale: post.og_locale || 'ar_SA',
        twitter_card: post.twitter_card || 'summary_large_image',
        twitter_title: post.twitter_title || '',
        twitter_description: post.twitter_description || '',
        twitter_image: post.twitter_image || '',
        twitter_creator: post.twitter_creator || '',
        schema_type: post.schema_type || 'BlogPosting',
        schema_headline: post.schema_headline || '',
        schema_description: post.schema_description || '',
        schema_image: post.schema_image || '',
        schema_author_name: post.schema_author_name || '',
        schema_author_url: post.schema_author_url || '',
        _method: 'put',
    });

    const fieldErrors = { ...pageErrors, ...errors };

    const toggleTag = (tagId) => {
        const current = data.tag_ids || [];
        if (current.includes(tagId)) {
            setData(
                'tag_ids',
                current.filter((id) => id !== tagId),
            );
        } else {
            setData('tag_ids', [...current, tagId]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        submitPost(route('admin.blog-posts.update', post.id), {
            forceFormData: true,
        });
    };

    const deleteImage = () => {
        if (deletingImage) {
            return;
        }
        setDeletingImage(true);
        router.delete(route('admin.blog-posts.delete-image', post.id), {
            preserveScroll: true,
            onFinish: () => setDeletingImage(false),
        });
    };

    return (
        <AdminUiPage
            title="تعديل المقال"
            subtitle={post.title}
            flash={flash}
            actions={
                <Link href={route('admin.blog-posts.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات المقال" icon="ri-article-line">
                <form onSubmit={submit}>
                            <ul className="nav nav-tabs mb-4" role="tablist">
                                {[
                                    { id: 'general', label: 'عام' },
                                    { id: 'seo', label: 'SEO' },
                                    { id: 'social', label: 'اجتماعي' },
                                    { id: 'schema', label: 'Schema' },
                                ].map((tab) => (
                                    <li className="nav-item" key={tab.id}>
                                        <button
                                            type="button"
                                            className={`nav-link${activeTab === tab.id ? ' active' : ''}`}
                                            onClick={() => setActiveTab(tab.id)}
                                        >
                                            {tab.label}
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            {activeTab === 'general' && (
                                <div className="row g-3">
                                    <div className="col-md-8">
                                        <label className="form-label">العنوان</label>
                                        <input
                                            type="text"
                                            className={`form-control${fieldErrors.title ? ' is-invalid' : ''}`}
                                            value={data.title}
                                            onChange={(e) => setData('title', e.target.value)}
                                        />
                                        {fieldErrors.title && (
                                            <div className="invalid-feedback">{fieldErrors.title}</div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label className="form-label">المسار (Slug)</label>
                                        <input
                                            type="text"
                                            dir="ltr"
                                            className={`form-control${fieldErrors.slug ? ' is-invalid' : ''}`}
                                            value={data.slug}
                                            onChange={(e) => setData('slug', e.target.value)}
                                        />
                                        {fieldErrors.slug && (
                                            <div className="invalid-feedback">{fieldErrors.slug}</div>
                                        )}
                                    </div>
                                    <div className="col-12">
                                        <label className="form-label">المقتطف</label>
                                        <textarea
                                            rows={2}
                                            className={`form-control${fieldErrors.excerpt ? ' is-invalid' : ''}`}
                                            value={data.excerpt}
                                            onChange={(e) => setData('excerpt', e.target.value)}
                                        />
                                        {fieldErrors.excerpt && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.excerpt}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-12">
                                        <label className="form-label">المحتوى</label>
                                        <TinyMceEditor
                                            value={data.content}
                                            onChange={(content) => setData('content', content)}
                                        />
                                        {fieldErrors.content && (
                                            <div className="text-danger small mt-1">
                                                {fieldErrors.content}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label className="form-label">التصنيف</label>
                                        <select
                                            className={`form-select${fieldErrors.blog_category_id ? ' is-invalid' : ''}`}
                                            value={data.blog_category_id}
                                            onChange={(e) =>
                                                setData('blog_category_id', e.target.value)
                                            }
                                        >
                                            <option value="">بدون تصنيف</option>
                                            {categories.map((category) => (
                                                <option key={category.id} value={category.id}>
                                                    {category.name}
                                                </option>
                                            ))}
                                        </select>
                                        {fieldErrors.blog_category_id && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.blog_category_id}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label className="form-label">الحالة</label>
                                        <select
                                            className={`form-select${fieldErrors.status ? ' is-invalid' : ''}`}
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                        >
                                            <option value="draft">مسودة</option>
                                            <option value="published">منشور</option>
                                            <option value="scheduled">مجدول</option>
                                            <option value="archived">مؤرشف</option>
                                        </select>
                                        {fieldErrors.status && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.status}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-4">
                                        <label className="form-label">اللغة</label>
                                        <select
                                            className={`form-select${fieldErrors.language ? ' is-invalid' : ''}`}
                                            value={data.language}
                                            onChange={(e) => setData('language', e.target.value)}
                                        >
                                            <option value="ar">العربية</option>
                                            <option value="en">English</option>
                                        </select>
                                        {fieldErrors.language && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.language}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-6">
                                        <MediaField
                                            label="الصورة البارزة"
                                            previewUrl={
                                                data.featured_image_preview ||
                                                (data.featured_image instanceof File
                                                    ? URL.createObjectURL(data.featured_image)
                                                    : null)
                                            }
                                            alt={data.featured_image_alt}
                                            error={
                                                fieldErrors.featured_image ||
                                                fieldErrors.featured_image_url
                                            }
                                            onPick={(item) => {
                                                setData({
                                                    ...data,
                                                    featured_image: null,
                                                    featured_image_url: item.url,
                                                    featured_image_preview: item.url,
                                                    featured_image_alt:
                                                        data.featured_image_alt || item.alt || '',
                                                });
                                            }}
                                            onFile={(file) => {
                                                setData({
                                                    ...data,
                                                    featured_image: file,
                                                    featured_image_url: '',
                                                    featured_image_preview: file
                                                        ? URL.createObjectURL(file)
                                                        : data.featured_image_preview,
                                                });
                                            }}
                                            onClear={() => {
                                                if (post.featured_image_url && can('admin.blog-posts.edit')) {
                                                    deleteImage();
                                                }
                                                setData({
                                                    ...data,
                                                    featured_image: null,
                                                    featured_image_url: '',
                                                    featured_image_preview: '',
                                                });
                                            }}
                                        />
                                        {deletingImage && (
                                            <div className="small text-muted mt-1">جاري حذف الصورة...</div>
                                        )}
                                    </div>
                                    <div className="col-md-6">
                                        <label className="form-label">نص بديل للصورة</label>
                                        <input
                                            type="text"
                                            className={`form-control${fieldErrors.featured_image_alt ? ' is-invalid' : ''}`}
                                            value={data.featured_image_alt}
                                            onChange={(e) =>
                                                setData('featured_image_alt', e.target.value)
                                            }
                                        />
                                        {fieldErrors.featured_image_alt && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.featured_image_alt}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-6">
                                        <label className="form-label">تاريخ النشر</label>
                                        <input
                                            type="datetime-local"
                                            dir="ltr"
                                            className={`form-control${fieldErrors.published_at ? ' is-invalid' : ''}`}
                                            value={data.published_at}
                                            onChange={(e) =>
                                                setData('published_at', e.target.value)
                                            }
                                        />
                                        {fieldErrors.published_at && (
                                            <div className="invalid-feedback">
                                                {fieldErrors.published_at}
                                            </div>
                                        )}
                                    </div>
                                    <div className="col-md-6 d-flex align-items-end">
                                        <label className="d-inline-flex align-items-center gap-2 mb-2">
                                            <input
                                                type="checkbox"
                                                checked={data.is_featured}
                                                onChange={(e) =>
                                                    setData('is_featured', e.target.checked)
                                                }
                                            />
                                            مقال مميز
                                        </label>
                                    </div>
                                    <div className="col-12">
                                        <label className="form-label">الوسوم</label>
                                        <div className="border rounded-3 p-3 d-flex flex-wrap gap-3">
                                            {tags.length === 0 && (
                                                <span className="text-muted small">
                                                    لا توجد وسوم متاحة
                                                </span>
                                            )}
                                            {tags.map((tag) => (
                                                <label
                                                    key={tag.id}
                                                    className="d-inline-flex align-items-center gap-2 mb-0"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={(data.tag_ids || []).includes(
                                                            tag.id,
                                                        )}
                                                        onChange={() => toggleTag(tag.id)}
                                                    />
                                                    {tag.name}
                                                </label>
                                            ))}
                                        </div>
                                        {fieldErrors.tag_ids && (
                                            <div className="text-danger small mt-1">
                                                {fieldErrors.tag_ids}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {activeTab === 'seo' && (
                                <BlogSeoFields
                                    data={data}
                                    setData={setData}
                                    fieldErrors={fieldErrors}
                                />
                            )}

                            {activeTab === 'social' && (
                                <BlogSocialSeoFields
                                    data={data}
                                    setData={setData}
                                    fieldErrors={fieldErrors}
                                />
                            )}

                            {activeTab === 'schema' && (
                                <BlogSchemaFields
                                    data={data}
                                    setData={setData}
                                    fieldErrors={fieldErrors}
                                />
                            )}

                            <div className="d-flex gap-2 mt-4">
                                <button
                                    type="submit"
                                    className="admin-btn admin-btn-primary"
                                    disabled={processing}
                                >
                                    <i className="ri-save-line" />
                                    حفظ التعديلات
                                </button>
                                <Link href={route('admin.blog-posts.index')} className="admin-btn admin-btn-ghost">
                                    إلغاء
                                </Link>
                            </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
