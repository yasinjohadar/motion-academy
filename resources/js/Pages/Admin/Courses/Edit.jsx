import AdminUiPage from '@/Components/Admin/AdminUiPage';
import CourseForm from '@/Components/Admin/CourseForm';
import { Link } from '@inertiajs/react';

export default function Edit({ course, flash, ...options }) {
    return (
        <AdminUiPage
            title="تعديل الدورة"
            subtitle={course.title}
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            chips={[
                { label: 'الحالة', value: course.status_label, icon: 'ri-flag-line' },
                { label: 'الدروس', value: course.lessons_count, icon: 'ri-play-list-2-line' },
            ]}
            actions={
                <>
                    <Link
                        href={route('admin.courses.curriculum', course.id)}
                        className="admin-btn admin-btn-primary"
                    >
                        <i className="ri-list-check-3" />
                        المنهج
                    </Link>
                    <Link href={route('admin.courses.index')} className="admin-btn admin-btn-ghost">
                        <i className="ri-arrow-right-line" />
                        العودة للقائمة
                    </Link>
                </>
            }
        >
            <CourseForm course={course} {...options} />
        </AdminUiPage>
    );
}
