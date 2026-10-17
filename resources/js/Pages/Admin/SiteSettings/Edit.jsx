import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import MediaField from '@/Components/Admin/MediaField';
import SearchPreview from '@/Components/Admin/SearchPreview';
import SocialLinksEditor from '@/Components/Admin/SocialLinksEditor';
import useCan from '@/hooks/useCan';
import { useForm } from '@inertiajs/react';

export default function Edit({ setting, socialLinks, iconOptions, flash }) {
    const { can } = useCan();
    const { data, setData, post, processing, errors } = useForm({
        site_name: setting.site_name || '',
        site_name_en: setting.site_name_en || '',
        tagline: setting.tagline || '',
        primary_whatsapp: setting.primary_whatsapp || '',
        email: setting.email || '',
        phone: setting.phone || '',
        address: setting.address || '',
        footer_text: setting.footer_text || '',
        logo: null,
        favicon: null,
        logo_url: '',
        favicon_url: '',
        logo_preview: setting.logo_url || '',
        favicon_preview: setting.favicon_url || '',
        remove_logo: false,
        remove_favicon: false,
        seo_title: setting.seo_title || '',
        seo_description: setting.seo_description || '',
        seo_keywords: setting.seo_keywords || '',
        seo_og_image: setting.seo_og_image || '',
        twitter_handle: setting.twitter_handle || '',
        google_site_verification: setting.google_site_verification || '',
        bing_site_verification: setting.bing_site_verification || '',
        allow_indexing: setting.allow_indexing !== false,
        social_links: socialLinks || [],
        _method: 'put',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.site-settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminUiPage
            title="إعدادات الموقع العامة"
            subtitle="الاسم، الشعار، رقم الواتساب الأساسي، ووسائل التواصل"
            flash={flash}
        >
            <form onSubmit={submit}>
                <AdminPanel title="الهوية" icon="ri-global-line" className="mb-3">
                    <div className="row g-3">
                        <div className="col-md-6">
                            <label className="form-label">اسم الموقع</label>
                            <input
                                className={`form-control${errors.site_name ? ' is-invalid' : ''}`}
                                value={data.site_name}
                                onChange={(e) => setData('site_name', e.target.value)}
                                required
                            />
                            {errors.site_name && <div className="invalid-feedback">{errors.site_name}</div>}
                        </div>
                        <div className="col-md-6">
                            <label className="form-label">الاسم بالإنكليزية</label>
                            <input
                                className="form-control"
                                value={data.site_name_en}
                                onChange={(e) => setData('site_name_en', e.target.value)}
                            />
                        </div>
                        <div className="col-12">
                            <label className="form-label">شعار مختصر</label>
                            <input
                                className="form-control"
                                value={data.tagline}
                                onChange={(e) => setData('tagline', e.target.value)}
                            />
                        </div>
                        <div className="col-md-6">
                            <MediaField
                                label="اللوغو"
                                previewUrl={
                                    data.remove_logo
                                        ? null
                                        : data.logo_preview ||
                                          (data.logo instanceof File ? URL.createObjectURL(data.logo) : null)
                                }
                                error={errors.logo || errors.logo_url}
                                onPick={(item) => {
                                    setData({
                                        ...data,
                                        logo: null,
                                        logo_url: item.url,
                                        logo_preview: item.url,
                                        remove_logo: false,
                                    });
                                }}
                                onFile={(file) => {
                                    setData({
                                        ...data,
                                        logo: file,
                                        logo_url: '',
                                        logo_preview: file ? URL.createObjectURL(file) : '',
                                        remove_logo: false,
                                    });
                                }}
                                onClear={() => {
                                    setData({
                                        ...data,
                                        logo: null,
                                        logo_url: '',
                                        logo_preview: '',
                                        remove_logo: true,
                                    });
                                }}
                            />
                        </div>
                        <div className="col-md-6">
                            <MediaField
                                label="أيقونة المتصفح (Favicon)"
                                previewUrl={
                                    data.remove_favicon
                                        ? null
                                        : data.favicon_preview ||
                                          (data.favicon instanceof File
                                              ? URL.createObjectURL(data.favicon)
                                              : null)
                                }
                                error={errors.favicon || errors.favicon_url}
                                onPick={(item) => {
                                    setData({
                                        ...data,
                                        favicon: null,
                                        favicon_url: item.url,
                                        favicon_preview: item.url,
                                        remove_favicon: false,
                                    });
                                }}
                                onFile={(file) => {
                                    setData({
                                        ...data,
                                        favicon: file,
                                        favicon_url: '',
                                        favicon_preview: file ? URL.createObjectURL(file) : '',
                                        remove_favicon: false,
                                    });
                                }}
                                onClear={() => {
                                    setData({
                                        ...data,
                                        favicon: null,
                                        favicon_url: '',
                                        favicon_preview: '',
                                        remove_favicon: true,
                                    });
                                }}
                            />
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="التواصل" icon="ri-phone-line" className="mb-3">
                    <div className="row g-3">
                        <div className="col-md-4">
                            <label className="form-label">رقم واتساب الأساسي</label>
                            <input
                                className="form-control"
                                value={data.primary_whatsapp}
                                onChange={(e) => setData('primary_whatsapp', e.target.value)}
                                placeholder="9665xxxxxxxx"
                                dir="ltr"
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">الهاتف</label>
                            <input
                                className="form-control"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                dir="ltr"
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">البريد</label>
                            <input
                                type="email"
                                className={`form-control${errors.email ? ' is-invalid' : ''}`}
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                dir="ltr"
                            />
                            {errors.email && <div className="invalid-feedback">{errors.email}</div>}
                        </div>
                        <div className="col-12">
                            <label className="form-label">العنوان</label>
                            <input
                                className="form-control"
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                            />
                        </div>
                        <div className="col-12">
                            <label className="form-label">نص التذييل</label>
                            <textarea
                                className="form-control"
                                rows={2}
                                value={data.footer_text}
                                onChange={(e) => setData('footer_text', e.target.value)}
                            />
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="محركات البحث (SEO)" icon="ri-search-eye-line" className="mb-3">
                    <div className="row g-3">
                        <div className="col-lg-7">
                            <div className="mb-3">
                                <label className="form-label">عنوان الصفحة الرئيسية</label>
                                <input
                                    className={`form-control${errors.seo_title ? ' is-invalid' : ''}`}
                                    value={data.seo_title}
                                    onChange={(e) => setData('seo_title', e.target.value)}
                                    placeholder={`${data.site_name} — ${data.tagline || 'منصة تعليمية'}`}
                                />
                                {errors.seo_title && (
                                    <div className="invalid-feedback">{errors.seo_title}</div>
                                )}
                                <div className="form-text">
                                    باقي الصفحات تأخذ: «عنوان الصفحة | {data.site_name}».
                                </div>
                            </div>
                            <div className="mb-3">
                                <label className="form-label">الوصف الافتراضي</label>
                                <textarea
                                    rows={3}
                                    className={`form-control${errors.seo_description ? ' is-invalid' : ''}`}
                                    value={data.seo_description}
                                    onChange={(e) => setData('seo_description', e.target.value)}
                                    placeholder="جملتان تشرحان ما يقدّمه الموقع، وتظهران تحت العنوان في نتائج البحث."
                                />
                                {errors.seo_description && (
                                    <div className="invalid-feedback">{errors.seo_description}</div>
                                )}
                                <div className="form-text">يُستخدم للرئيسية ولأي صفحة ليس لها وصف خاص.</div>
                            </div>
                            <div>
                                <label className="form-label">الكلمات المفتاحية</label>
                                <input
                                    className="form-control"
                                    value={data.seo_keywords}
                                    onChange={(e) => setData('seo_keywords', e.target.value)}
                                    placeholder="تحريك, موشن جرافيك, دورات أونلاين"
                                />
                            </div>
                        </div>
                        <div className="col-lg-5">
                            <SearchPreview
                                title={data.seo_title || `${data.site_name} — ${data.tagline || ''}`}
                                description={data.seo_description || data.tagline}
                                url={window.location.origin}
                                titleLength={
                                    (data.seo_title || `${data.site_name} — ${data.tagline || ''}`).length
                                }
                                descriptionLength={(data.seo_description || data.tagline || '').length}
                                indexable={data.allow_indexing}
                                onIndexableChange={(value) => setData('allow_indexing', value)}
                            />
                            {!data.allow_indexing && (
                                <div className="admin-alert admin-alert-warning mt-2 mb-0">
                                    الموقع كله مخفي عن محركات البحث (robots.txt يمنع الزحف). مناسب لنسخة
                                    تجريبية فقط.
                                </div>
                            )}
                        </div>
                        <div className="col-lg-6">
                            <MediaField
                                label="صورة المشاركة الافتراضية (1200×630)"
                                previewUrl={data.seo_og_image || null}
                                allowUpload={false}
                                error={errors.seo_og_image}
                                onPick={(item) => setData('seo_og_image', item.url)}
                                onClear={() => setData('seo_og_image', '')}
                            />
                            <div className="form-text">تظهر عند مشاركة رابط صفحة ليس لها صورة خاصة.</div>
                        </div>
                        <div className="col-lg-6">
                            <div className="mb-3">
                                <label className="form-label">حساب X (تويتر)</label>
                                <input
                                    className={`form-control${errors.twitter_handle ? ' is-invalid' : ''}`}
                                    value={data.twitter_handle}
                                    onChange={(e) => setData('twitter_handle', e.target.value)}
                                    placeholder="@MotionAcademy"
                                    dir="ltr"
                                />
                                {errors.twitter_handle && (
                                    <div className="invalid-feedback">{errors.twitter_handle}</div>
                                )}
                            </div>
                            <div className="mb-3">
                                <label className="form-label">رمز تحقق Google Search Console</label>
                                <input
                                    className="form-control"
                                    value={data.google_site_verification}
                                    onChange={(e) => setData('google_site_verification', e.target.value)}
                                    placeholder={'<meta name="google-site-verification" content="…">'}
                                    dir="ltr"
                                />
                            </div>
                            <div>
                                <label className="form-label">رمز تحقق Bing Webmaster</label>
                                <input
                                    className="form-control"
                                    value={data.bing_site_verification}
                                    onChange={(e) => setData('bing_site_verification', e.target.value)}
                                    placeholder={'<meta name="msvalidate.01" content="…">'}
                                    dir="ltr"
                                />
                            </div>
                            <div className="form-text">
                                الصق الوسم كاملاً أو الرمز فقط — يُستخرج الرمز تلقائياً.
                            </div>
                        </div>
                        <div className="col-12">
                            <div className="seo-links">
                                <a href="/sitemap.xml" target="_blank" rel="noopener">
                                    <i className="ri-node-tree" /> خريطة الموقع sitemap.xml
                                </a>
                                <a href="/robots.txt" target="_blank" rel="noopener">
                                    <i className="ri-robot-2-line" /> robots.txt
                                </a>
                                <span className="text-muted small">
                                    أرسل رابط خريطة الموقع إلى Google Search Console وBing بعد التحقق.
                                </span>
                            </div>
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="وسائل التواصل" icon="ri-share-line" className="mb-3">
                    <SocialLinksEditor
                        links={data.social_links}
                        iconOptions={iconOptions}
                        errors={errors}
                        onChange={(links) => setData('social_links', links)}
                    />
                </AdminPanel>

                {can('admin.site-settings.edit') && (
                    <button type="submit" className="btn btn-primary" disabled={processing}>
                        حفظ الإعدادات
                    </button>
                )}
            </form>
        </AdminUiPage>
    );
}
