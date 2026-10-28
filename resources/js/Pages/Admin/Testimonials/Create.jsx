import AdminUiPage from '@/Components/Admin/AdminUiPage';
import TestimonialForm, { testimonialFormData } from '@/Components/Admin/TestimonialForm';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ courses, nextSortOrder }) {
    const { data, setData, post, processing, errors } = useForm(
        testimonialFormData(null, { sort_order: nextSortOrder }),
    );

    return (
        <AdminUiPage
            title="رأي جديد"
            subtitle="أضف تجربة متعلم إلى قسم الآراء"
            actions={
                <Link href={route('admin.testimonials.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <TestimonialForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                courses={courses}
                submitLabel="إضافة الرأي"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.testimonials.store'), {
                        forceFormData: data.avatar instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
