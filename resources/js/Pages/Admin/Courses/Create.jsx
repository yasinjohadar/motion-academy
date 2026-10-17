import AdminUiPage from '@/Components/Admin/AdminUiPage';
import CourseForm from '@/Components/Admin/CourseForm';
import { Link } from '@inertiajs/react';

export default function Create({ flash, ...options }) {
    return (
        <AdminUiPage
            title="دورة جديدة"
            subtitle="أدخل بيانات الدورة، ثم أضف أقسامها ودروسها في الخطوة التالية"
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            actions={
                <Link href={route('admin.courses.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <CourseForm {...options} />
        </AdminUiPage>
    );
}
