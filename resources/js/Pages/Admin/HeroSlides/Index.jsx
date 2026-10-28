import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const EFFECT_LABELS = {
    fade: 'تلاشي',
    slide: 'انزلاق',
    creative: 'انزلاق مع عمق',
};

function SliderSettings({ settings, effects, canEdit }) {
    const { data, setData, put, processing, errors, isDirty, setDefaults } = useForm(settings);

    const toggle = (key, label) => (
        <div className="col-sm-6 col-lg-4">
            <label className="form-check mb-0">
                <input
                    type="checkbox"
                    className="form-check-input"
                    checked={Boolean(data[key])}
                    disabled={!canEdit}
                    onChange={(e) => setData(key, e.target.checked)}
                />
                <span className="form-check-label">{label}</span>
            </label>
        </div>
    );

    const number = (key, label, props) => (
        <div className="col-sm-6 col-lg-3">
            <label className="form-label">{label}</label>
            <input
                type="number"
                className={`form-control${errors[key] ? ' is-invalid' : ''}`}
                value={data[key]}
                disabled={!canEdit}
                onChange={(e) => setData(key, e.target.value === '' ? '' : Number(e.target.value))}
                {...props}
            />
            {errors[key] && <div className="invalid-feedback">{errors[key]}</div>}
        </div>
    );

    return (
        <AdminPanel title="إعدادات السلايدر" icon="ri-equalizer-line" className="mb-4">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    put(route('admin.hero-slides.settings'), {
                        preserveScroll: true,
                        onSuccess: () => setDefaults(),
                    });
                }}
            >
                <div className="row g-3">
                    {number('autoplay_delay', 'مدة عرض الشريحة (ملي ثانية)', { min: 1500, max: 30000, step: 500 })}
                    {number('speed', 'سرعة الانتقال (ملي ثانية)', { min: 200, max: 3000, step: 100 })}
                    {number('max_slides', 'أقصى عدد للشرائح (0 = الكل)', { min: 0, max: 50 })}
                    <div className="col-sm-6 col-lg-3">
                        <label className="form-label">تأثير الانتقال</label>
                        <select
                            className={`form-select${errors.effect ? ' is-invalid' : ''}`}
                            value={data.effect}
                            disabled={!canEdit}
                            onChange={(e) => setData('effect', e.target.value)}
                        >
                            {effects.map((effect) => (
                                <option key={effect} value={effect}>
                                    {EFFECT_LABELS[effect] || effect}
                                </option>
                            ))}
                        </select>
                    </div>
                    {toggle('autoplay', 'تشغيل تلقائي')}
                    {toggle('loop', 'تكرار لا نهائي')}
                    {toggle('pause_on_hover', 'إيقاف مؤقت عند مرور الفأرة')}
                    {toggle('show_navigation', 'إظهار أسهم التنقل')}
                    {toggle('show_pagination', 'إظهار النقاط وعناوينها')}
                    {toggle('show_progress', 'إظهار شريط التقدم')}
                </div>
                {canEdit && (
                    <div className="mt-3">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing || !isDirty}
                        >
                            <i className="ri-save-line" />
                            حفظ الإعدادات
                        </button>
                    </div>
                )}
            </form>
        </AdminPanel>
    );
}

export default function Index({ slides, settings, effects, flash }) {
    const { can } = useCan();
    const [slideToDelete, setSlideToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const showingCount = slides.filter((slide) => slide.is_showing).length;
    const shownCount =
        settings.max_slides > 0 ? Math.min(showingCount, settings.max_slides) : showingCount;

    return (
        <AdminUiPage
            title="سلايدر الصفحة الرئيسية"
            subtitle={`${slides.length} شريحة — يظهر منها ${shownCount} الآن`}
            flash={flash}
            actions={
                can('admin.hero-slides.create') && (
                    <Link href={route('admin.hero-slides.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-add-line" />
                        شريحة جديدة
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(slideToDelete)}
                message={slideToDelete ? `هل أنت متأكد من حذف الشريحة «${slideToDelete.title}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!slideToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.hero-slides.destroy', slideToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setSlideToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setSlideToDelete(null)}
            />

            <SliderSettings settings={settings} effects={effects} canEdit={can('admin.hero-slides.edit')} />

            <AdminPanel title="الشرائح" icon="ri-slideshow-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الترتيب</th>
                                    <th>الشريحة</th>
                                    <th>العنصر المرئي</th>
                                    <th>الحالة</th>
                                    <th>آخر تحديث</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {slides.map((slide) => (
                                    <tr key={slide.id}>
                                        <td>{slide.sort_order}</td>
                                        <td>
                                            <div className="d-flex align-items-center gap-2">
                                                {slide.visual === 'image' && slide.image_url && (
                                                    <img
                                                        src={slide.image_url}
                                                        alt=""
                                                        width={56}
                                                        height={36}
                                                        className="rounded object-fit-cover"
                                                    />
                                                )}
                                                <div>
                                                    <strong style={{ whiteSpace: 'pre-line' }}>{slide.title}</strong>
                                                    {slide.badge_text && (
                                                        <div className="small text-muted">{slide.badge_text}</div>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td>{slide.visual_label}</td>
                                        <td>
                                            {!slide.is_active ? (
                                                <span className="badge bg-secondary">موقوفة</span>
                                            ) : slide.is_showing ? (
                                                <span className="badge bg-success">تظهر الآن</span>
                                            ) : (
                                                <span className="badge bg-warning text-dark">خارج فترة العرض</span>
                                            )}
                                        </td>
                                        <td dir="ltr">{slide.updated_at}</td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-1">
                                                {slide.can?.update && (
                                                    <>
                                                        <Link
                                                            href={route('admin.hero-slides.edit', slide.id)}
                                                            className="btn btn-sm btn-outline-primary"
                                                        >
                                                            تعديل
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="btn btn-sm btn-outline-secondary"
                                                            onClick={() =>
                                                                router.post(
                                                                    route('admin.hero-slides.toggle-active', slide.id),
                                                                    {},
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                        >
                                                            {slide.is_active ? 'إيقاف' : 'تفعيل'}
                                                        </button>
                                                    </>
                                                )}
                                                {slide.can?.delete && (
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger"
                                                        onClick={() => setSlideToDelete(slide)}
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {slides.length === 0 && (
                                    <TableEmptyRow colSpan={6}>
                                        لا توجد شرائح — لن يظهر السلايدر في الصفحة الرئيسية حتى تضيف واحدة.
                                    </TableEmptyRow>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </AdminPanel>
        </AdminUiPage>
    );
}
