import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import { Stars } from '@/Components/Admin/TestimonialForm';
import useCan from '@/hooks/useCan';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function SectionSettings({ settings, options, counts, canEdit }) {
    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm(settings);

    const available = data.featured_only ? counts.featured : counts.active;
    const shown = Number(data.limit) > 0 ? Math.min(Number(data.limit), available) : available;

    const select = (key, label, list, cast = String) => (
        <div className="col-sm-6 col-lg-3">
            <label className="form-label">{label}</label>
            <select
                className={`form-select${errors[key] ? ' is-invalid' : ''}`}
                value={data[key]}
                disabled={!canEdit}
                onChange={(e) => setData(key, cast(e.target.value))}
            >
                {list.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {errors[key] && <div className="invalid-feedback">{errors[key]}</div>}
        </div>
    );

    const toggle = (key, label, disabled = false) => (
        <label className="form-check mb-0">
            <input
                type="checkbox"
                className="form-check-input"
                checked={Boolean(data[key])}
                disabled={!canEdit || disabled}
                onChange={(e) => setData(key, e.target.checked)}
            />
            <span className="form-check-label">{label}</span>
        </label>
    );

    return (
        <AdminPanel title="إعدادات القسم في الصفحة الرئيسية" icon="ri-layout-grid-line" className="mb-4">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    put(route('admin.testimonials.settings'), {
                        preserveScroll: true,
                        onSuccess: () => setDefaults(),
                    });
                }}
            >
                <div className="row g-3">
                    <div className="col-md-3">
                        <label className="form-label">العنوان الصغير</label>
                        <input
                            className={`form-control${errors.eyebrow ? ' is-invalid' : ''}`}
                            value={data.eyebrow}
                            disabled={!canEdit}
                            onChange={(e) => setData('eyebrow', e.target.value)}
                        />
                    </div>
                    <div className="col-md-4">
                        <label className="form-label">عنوان القسم</label>
                        <input
                            className={`form-control${errors.title ? ' is-invalid' : ''}`}
                            value={data.title}
                            disabled={!canEdit}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                        />
                        {errors.title && <div className="invalid-feedback">{errors.title}</div>}
                    </div>
                    <div className="col-md-5">
                        <label className="form-label">وصف القسم</label>
                        <input
                            className={`form-control${errors.subtitle ? ' is-invalid' : ''}`}
                            value={data.subtitle}
                            disabled={!canEdit}
                            onChange={(e) => setData('subtitle', e.target.value)}
                        />
                    </div>

                    <div className="col-sm-6 col-lg-3">
                        <label className="form-label">عدد الآراء في الرئيسية</label>
                        <input
                            type="number"
                            min={0}
                            max={24}
                            className={`form-control${errors.limit ? ' is-invalid' : ''}`}
                            value={data.limit}
                            disabled={!canEdit}
                            onChange={(e) => setData('limit', e.target.value === '' ? '' : Number(e.target.value))}
                        />
                        <div className="form-text">
                            0 = كل الآراء المتاحة — سيظهر الآن {shown} من {available}
                        </div>
                        {errors.limit && <div className="invalid-feedback d-block">{errors.limit}</div>}
                    </div>
                    {select('order', 'ترتيب العرض', options.orders)}
                    {select('layout', 'طريقة العرض', options.layouts)}
                    {select('columns', 'عدد الأعمدة', options.columns, Number)}

                    <div className="col-12 d-flex flex-wrap gap-4">
                        {toggle('featured_only', 'الآراء المميزة فقط')}
                        {toggle('show_rating', 'إظهار النجوم')}
                        {toggle('autoplay', 'تشغيل تلقائي للسلايدر', data.layout !== 'slider')}
                    </div>
                </div>
                {canEdit && (
                    <div className="mt-3">
                        <button type="submit" className="admin-btn admin-btn-primary" disabled={processing || !isDirty}>
                            <i className="ri-save-line" />
                            حفظ الإعدادات
                        </button>
                    </div>
                )}
            </form>
        </AdminPanel>
    );
}

export default function Index({ testimonials, counts, settings, options, filters, flash }) {
    const { can } = useCan();
    const [query, setQuery] = useState(filters.query || '');
    const [status, setStatus] = useState(filters.status || '');
    const [toDelete, setToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        if (query === (filters.query || '') && status === (filters.status || '')) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.testimonials.index'),
                { ...(query ? { query } : {}), ...(status ? { status } : {}) },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [query, status, filters.query, filters.status]);

    const post = (name, testimonial) =>
        router.post(route(name, testimonial.id), {}, { preserveScroll: true });

    return (
        <AdminUiPage
            title="آراء المتعلمين"
            subtitle={`${counts.all} رأي — ${counts.active} ظاهر، ${counts.featured} مميز`}
            flash={flash}
            actions={
                can('admin.testimonials.create') && (
                    <Link href={route('admin.testimonials.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-add-line" />
                        رأي جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(toDelete)}
                message={toDelete ? `هل أنت متأكد من حذف رأي «${toDelete.name}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!toDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.testimonials.destroy', toDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setToDelete(null)}
            />

            <SectionSettings
                settings={settings}
                options={options}
                counts={counts}
                canEdit={can('admin.testimonials.edit')}
            />

            <AdminPanel title="قائمة الآراء" icon="ri-chat-quote-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالاسم أو المسمى أو النص..."
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select"
                            style={{ maxWidth: 180 }}
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">كل الحالات</option>
                            <option value="active">ظاهرة</option>
                            <option value="inactive">مخفية</option>
                            <option value="featured">مميزة</option>
                        </select>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الترتيب</th>
                                    <th>المتعلم</th>
                                    <th>الرأي</th>
                                    <th>التقييم</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(testimonials.data || []).map((testimonial) => (
                                    <tr key={testimonial.id}>
                                        <td>{testimonial.sort_order}</td>
                                        <td>
                                            <div className="d-flex align-items-center gap-2">
                                                <span
                                                    className="rounded-circle d-inline-flex align-items-center justify-content-center overflow-hidden flex-shrink-0"
                                                    style={{
                                                        width: 40,
                                                        height: 40,
                                                        background: `${testimonial.color || '#02ad8f'}22`,
                                                        color: testimonial.color || '#02ad8f',
                                                    }}
                                                >
                                                    {testimonial.avatar_url ? (
                                                        <img
                                                            src={testimonial.avatar_url}
                                                            alt=""
                                                            className="w-100 h-100 object-fit-cover"
                                                        />
                                                    ) : (
                                                        <i className="ri-user-3-line" />
                                                    )}
                                                </span>
                                                <div>
                                                    <strong>{testimonial.name}</strong>
                                                    <div className="small text-muted">
                                                        {[testimonial.role, testimonial.company].filter(Boolean).join(' · ')}
                                                    </div>
                                                    {testimonial.course && (
                                                        <div className="small text-primary">
                                                            <i className="ri-graduation-cap-line" /> {testimonial.course}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ maxWidth: 360 }}>
                                            <span className="small text-muted d-block text-truncate">
                                                {testimonial.content}
                                            </span>
                                        </td>
                                        <td>
                                            <Stars rating={testimonial.rating} />
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-1">
                                                <span
                                                    className={`badge ${testimonial.is_active ? 'bg-success' : 'bg-secondary'}`}
                                                >
                                                    {testimonial.is_active ? 'ظاهر' : 'مخفي'}
                                                </span>
                                                {testimonial.is_featured && (
                                                    <span className="badge bg-warning text-dark">مميز</span>
                                                )}
                                            </div>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-1">
                                                {testimonial.can?.update && (
                                                    <>
                                                        <Link
                                                            href={route('admin.testimonials.edit', testimonial.id)}
                                                            className="btn btn-sm btn-outline-primary"
                                                        >
                                                            تعديل
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-secondary"
                                                            onClick={() => post('admin.testimonials.toggle-active', testimonial)}
                                                        >
                                                            {testimonial.is_active ? 'إخفاء' : 'إظهار'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-warning"
                                                            title={testimonial.is_featured ? 'إلغاء التمييز' : 'تمييز'}
                                                            onClick={() => post('admin.testimonials.toggle-featured', testimonial)}
                                                        >
                                                            <i className={testimonial.is_featured ? 'ri-star-fill' : 'ri-star-line'} />
                                                        </button>
                                                    </>
                                                )}
                                                {testimonial.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger"
                                                        onClick={() => setToDelete(testimonial)}
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {(testimonials.data || []).length === 0 && (
                                    <TableEmptyRow colSpan={6}>لا توجد آراء.</TableEmptyRow>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination paginator={testimonials} />
            </AdminPanel>
        </AdminUiPage>
    );
}
