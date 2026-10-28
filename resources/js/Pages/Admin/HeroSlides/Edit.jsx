import AdminUiPage from '@/Components/Admin/AdminUiPage';
import HeroSlideForm, { heroSlideFormData } from '@/Components/Admin/HeroSlideForm';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ slide, visuals }) {
    const { data, setData, post, processing, errors } = useForm({
        ...heroSlideFormData(slide),
        _method: 'put',
    });

    return (
        <AdminUiPage
            title="تعديل الشريحة"
            subtitle={slide.title}
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
                submitLabel="حفظ التعديلات"
                onSubmit={(e) => {
                    e.preventDefault();
                    // POST + _method so an uploaded image travels as multipart.
                    post(route('admin.hero-slides.update', slide.id), {
                        forceFormData: data.image instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
