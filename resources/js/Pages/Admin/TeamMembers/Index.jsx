import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import Pagination from '@/Components/Admin/Pagination';
import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import { Stars } from '@/Components/Admin/TestimonialForm';
import useCan from '@/hooks/useCan';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function SectionSettings({ settings, options, activeCount, canEdit }) {
    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm(settings);

    const shown = Number(data.limit) > 0 ? Math.min(Number(data.limit), activeCount) : activeCount;

    const text = (key, label, className, props = {}) => (
        <div className={className}>
            <label className="form-label">{label}</label>
            <input
                className={`form-control${errors[key] ? ' is-invalid' : ''}`}
                value={data[key]}
                disabled={!canEdit}
                onChange={(e) => setData(key, e.target.value)}
                {...props}
            />
            {errors[key] && <div className="invalid-feedback">{errors[key]}</div>}
        </div>
    );

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
                    put(route('admin.team-members.settings'), {
                        preserveScroll: true,
                        onSuccess: () => setDefaults(),
                    });
                }}
            >
                <div className="row g-3">
                    {text('eyebrow', 'العنوان الصغير', 'col-md-3')}
                    {text('title', 'عنوان القسم', 'col-md-4', { required: true })}
                    {text('subtitle', 'وصف القسم', 'col-md-5')}

                    <div className="col-sm-6 col-lg-3">
                        <label className="form-label">عدد الأعضاء في الرئيسية</label>
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
                            0 = كل الأعضاء — سيظهر الآن {shown} من {activeCount}
                        </div>
                        {errors.limit && <div className="invalid-feedback d-block">{errors.limit}</div>}
                    </div>
                    {select('order', 'ترتيب العرض', options.orders)}
                    {select('layout', 'طريقة العرض', options.layouts)}
                    {select('columns', 'عدد الأعمدة', options.columns, Number)}

                    <div className="col-12 d-flex flex-wrap gap-4">
                        {toggle('show_rating', 'إظهار التقييم')}
                        {toggle('show_social', 'إظهار روابط التواصل')}
                        {toggle('autoplay', 'تشغيل تلقائي للسلايدر', data.layout !== 'slider')}
                    </div>

                    {text('cta_label', 'نص زر أسفل القسم', 'col-md-4', { placeholder: 'تعرف على كامل الفريق' })}
                    {text('cta_url', 'رابط الزر', 'col-md-5', { dir: 'ltr', placeholder: '/who-we-are' })}
                    <div className="col-md-3 d-flex align-items-end">
                        <span className="form-text">اترك النص والرابط فارغين لإخفاء الزر.</span>
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

export default function Index({ members, counts, settings, options, platforms, filters, flash }) {
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
                route('admin.team-members.index'),
                { ...(query ? { query } : {}), ...(status ? { status } : {}) },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [query, status, filters.query, filters.status]);

    const platformIcon = (value) => platforms.find((option) => option.value === value)?.icon || 'fas fa-link';

    return (
        <AdminUiPage
            title="فريق الخبراء"
            subtitle={`${counts.all} عضو — ${counts.active} ظاهر`}
            flash={flash}
            actions={
                can('admin.team-members.create') && (
                    <Link href={route('admin.team-members.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-user-add-line" />
                        عضو جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(toDelete)}
                message={toDelete ? `هل أنت متأكد من حذف «${toDelete.name}» من الفريق؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!toDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.team-members.destroy', toDelete.id), {
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
                activeCount={counts.active}
                canEdit={can('admin.team-members.edit')}
            />

            <AdminPanel title="أعضاء الفريق" icon="ri-team-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالاسم أو المسمى..."
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
                            <option value="active">ظاهرون</option>
                            <option value="inactive">مخفيون</option>
                        </select>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الترتيب</th>
                                    <th>العضو</th>
                                    <th>التقييم</th>
                                    <th>التواصل</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(members.data || []).map((member) => (
                                    <tr key={member.id}>
                                        <td>{member.sort_order}</td>
                                        <td>
                                            <div className="d-flex align-items-center gap-2">
                                                <span
                                                    className="rounded-circle d-inline-flex align-items-center justify-content-center overflow-hidden flex-shrink-0"
                                                    style={{
                                                        width: 42,
                                                        height: 42,
                                                        background: `${member.color || '#02ad8f'}22`,
                                                        color: member.color || '#02ad8f',
                                                    }}
                                                >
                                                    {member.avatar_url ? (
                                                        <img src={member.avatar_url} alt="" className="w-100 h-100 object-fit-cover" />
                                                    ) : (
                                                        <i className={member.icon || 'fas fa-user-tie'} />
                                                    )}
                                                </span>
                                                <div>
                                                    <strong>{member.name}</strong>
                                                    {member.title && (
                                                        <div className="small text-muted" dir="auto">
                                                            {member.title}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {member.rating !== null ? (
                                                <span className="d-inline-flex align-items-center gap-1">
                                                    <Stars rating={Math.round(member.rating * 2) / 2} />
                                                    <small className="fw-bold">{member.rating.toFixed(1)}</small>
                                                </span>
                                            ) : (
                                                <span className="text-muted">—</span>
                                            )}
                                        </td>
                                        <td>
                                            <div className="d-flex gap-2 text-muted">
                                                {member.social_links.map((link, index) => (
                                                    <a
                                                        key={index}
                                                        href={link.platform === 'email' ? `mailto:${link.url}` : link.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-reset"
                                                    >
                                                        <i className={platformIcon(link.platform)} />
                                                    </a>
                                                ))}
                                                {member.social_links.length === 0 && '—'}
                                            </div>
                                        </td>
                                        <td>
                                            <span className={`badge ${member.is_active ? 'bg-success' : 'bg-secondary'}`}>
                                                {member.is_active ? 'ظاهر' : 'مخفي'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-1">
                                                {member.can?.update && (
                                                    <>
                                                        <Link
                                                            href={route('admin.team-members.edit', member.id)}
                                                            className="btn btn-sm btn-outline-primary"
                                                        >
                                                            تعديل
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-secondary"
                                                            onClick={() =>
                                                                router.post(
                                                                    route('admin.team-members.toggle-active', member.id),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {member.is_active ? 'إخفاء' : 'إظهار'}
                                                        </button>
                                                    </>
                                                )}
                                                {member.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger"
                                                        onClick={() => setToDelete(member)}
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {(members.data || []).length === 0 && (
                                    <TableEmptyRow colSpan={6}>لا يوجد أعضاء.</TableEmptyRow>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination paginator={members} />
            </AdminPanel>
        </AdminUiPage>
    );
}
