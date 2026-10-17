import AdminUiPage from '@/Components/Admin/AdminUiPage';
import CourseCategoryForm from '@/Components/Admin/CourseCategoryForm';
import { Link } from '@inertiajs/react';

export default function Edit({ category, roots = [], flash }) {
    return (
        <AdminUiPage
            title="تعديل التصنيف"
            subtitle={category.name}
            eyebrow="الدورات"
            eyebrowIcon="ri-graduation-cap-line"
            flash={flash}
            actions={
                <Link href={route('admin.course-categories.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <CourseCategoryForm category={category} roots={roots} />
        </AdminUiPage>
    );
}
