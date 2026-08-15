import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function Create({ flash }) {
    const { errors: pageErrors } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        description: '',
        color: '',
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        is_active: true,
        is_indexable: true,
        order: 0,
    });

    const fieldErrors = { ...pageErrors, ...errors };

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.blog-tags.store'));
    };

    return (
        <AdminUiPage
            title="إنشاء وسم"
            subtitle="أضف وسماً جديداً لمقالات المدونة"
            flash={flash}
            actions={
                <Link href={route('admin.blog-tags.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات الوسم" icon="ri-price-tag-3-line">
                <form onSubmit={submit}>
                            <div className="row g-3">
                                <div className="col-md-6">
                                    <label className="form-label">الاسم</label>
                                    <input
                                        type="text"
                                        className={`form-control${fieldErrors.name ? ' is-invalid' : ''}`}
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    {fieldErrors.name && (
                                        <div className="invalid-feedback">{fieldErrors.name}</div>
                                    )}
                                </div>
                                <div className="col-md-6">
                                    <label className="form-label">المسار (Slug)</label>
                                    <input
                                        type="text"
                                        dir="ltr"
                                        className={`form-control${fieldErrors.slug ? ' is-invalid' : ''}`}
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        placeholder="اختياري — يُولَّد تلقائياً"
                                    />
                                    {fieldErrors.slug && (
                                        <div className="invalid-feedback">{fieldErrors.slug}</div>
                                    )}
                                </div>
                                <div className="col-md-4">
                                    <label className="form-label">اللون</label>
                                    <input
                                        type="text"
                                        dir="ltr"
                                        className={`form-control${fieldErrors.color ? ' is-invalid' : ''}`}
                                        value={data.color}
                                        onChange={(e) => setData('color', e.target.value)}
                                        placeholder="#0d6efd"
                                    />
                                    {fieldErrors.color && (
                                        <div className="invalid-feedback">{fieldErrors.color}</div>
                                    )}
                                </div>
                                <div className="col-md-4">
                                    <label className="form-label">الترتيب</label>
                                    <input
                                        type="number"
                                        min={0}
                                        className={`form-control${fieldErrors.order ? ' is-invalid' : ''}`}
                                        value={data.order}
                                        onChange={(e) => setData('order', e.target.value)}
                                    />
                                    {fieldErrors.order && (
                                        <div className="invalid-feedback">{fieldErrors.order}</div>
                                    )}
                                </div>
                                <div className="col-12">
                                    <label className="form-label">الوصف</label>
                                    <textarea
                                        rows={3}
                                        className={`form-control${fieldErrors.description ? ' is-invalid' : ''}`}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                    />
                                    {fieldErrors.description && (
                                        <div className="invalid-feedback">{fieldErrors.description}</div>
                                    )}
                                </div>

                                <div className="col-12">
                                    <hr className="my-1" />
                                    <h6 className="mb-0">إعدادات SEO</h6>
                                </div>
                                <div className="col-md-6">
                                    <label className="form-label">عنوان الميتا</label>
                                    <input
                                        type="text"
                                        className={`form-control${fieldErrors.meta_title ? ' is-invalid' : ''}`}
                                        value={data.meta_title}
                                        onChange={(e) => setData('meta_title', e.target.value)}
                                    />
                                    {fieldErrors.meta_title && (
                                        <div className="invalid-feedback">{fieldErrors.meta_title}</div>
                                    )}
                                </div>
                                <div className="col-md-6">
                                    <label className="form-label">الرابط القانوني</label>
                                    <input
                                        type="url"
                                        dir="ltr"
                                        className={`form-control${fieldErrors.canonical_url ? ' is-invalid' : ''}`}
                                        value={data.canonical_url}
                                        onChange={(e) => setData('canonical_url', e.target.value)}
                                    />
                                    {fieldErrors.canonical_url && (
                                        <div className="invalid-feedback">
                                            {fieldErrors.canonical_url}
                                        </div>
                                    )}
                                </div>
                                <div className="col-12">
                                    <label className="form-label">وصف الميتا</label>
                                    <textarea
                                        rows={2}
                                        className={`form-control${fieldErrors.meta_description ? ' is-invalid' : ''}`}
                                        value={data.meta_description}
                                        onChange={(e) =>
                                            setData('meta_description', e.target.value)
                                        }
                                    />
                                    {fieldErrors.meta_description && (
                                        <div className="invalid-feedback">
                                            {fieldErrors.meta_description}
                                        </div>
                                    )}
                                </div>

                                <div className="col-12 d-flex flex-wrap gap-4">
                                    <label className="d-inline-flex align-items-center gap-2 mb-0">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                        />
                                        نشط
                                    </label>
                                    <label className="d-inline-flex align-items-center gap-2 mb-0">
                                        <input
                                            type="checkbox"
                                            checked={data.is_indexable}
                                            onChange={(e) =>
                                                setData('is_indexable', e.target.checked)
                                            }
                                        />
                                        قابل للفهرسة
                                    </label>
                                </div>

                                <div className="col-12 d-flex gap-2">
                                    <button
                                        type="submit"
                                        className="admin-btn admin-btn-primary"
                                        disabled={processing}
                                    >
                                        <i className="ri-save-line" />
                                        حفظ الوسم
                                    </button>
                                    <Link href={route('admin.blog-tags.index')} className="admin-btn admin-btn-ghost">
                                        إلغاء
                                    </Link>
                                </div>
                            </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
