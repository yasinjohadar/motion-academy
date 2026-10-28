import AdminUiPage from '@/Components/Admin/AdminUiPage';
import TestimonialForm, { testimonialFormData } from '@/Components/Admin/TestimonialForm';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ testimonial, courses }) {
    const { data, setData, post, processing, errors } = useForm({
        ...testimonialFormData(testimonial),
        _method: 'put',
    });

    return (
        <AdminUiPage
            title="تعديل الرأي"
            subtitle={testimonial.name}
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
                submitLabel="حفظ التعديلات"
                onSubmit={(e) => {
                    e.preventDefault();
                    // POST + _method so an uploaded avatar travels as multipart.
                    post(route('admin.testimonials.update', testimonial.id), {
                        forceFormData: data.avatar instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
