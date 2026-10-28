import AdminUiPage from '@/Components/Admin/AdminUiPage';
import HeroSlideForm, { heroSlideFormData } from '@/Components/Admin/HeroSlideForm';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ visuals, nextSortOrder }) {
    const { data, setData, post, processing, errors } = useForm(
        heroSlideFormData(null, { sort_order: nextSortOrder }),
    );

    return (
        <AdminUiPage
            title="شريحة جديدة"
            subtitle="أضف شريحة إلى سلايدر الصفحة الرئيسية"
            actions={
                <Link href={route('admin.hero-slides.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <HeroSlideForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                visuals={visuals}
                submitLabel="إضافة الشريحة"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.hero-slides.store'), {
                        forceFormData: data.image instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
