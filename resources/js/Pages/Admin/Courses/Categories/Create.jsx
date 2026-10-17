import AdminUiPage from '@/Components/Admin/AdminUiPage';
import CourseCategoryForm from '@/Components/Admin/CourseCategoryForm';
import { Link } from '@inertiajs/react';

export default function Create({ roots = [], defaultParentId = null, flash }) {
    return (
        <AdminUiPage
            title="تصنيف دورات جديد"
            subtitle="أضف تصنيفاً رئيسياً أو فرعياً لتنظيم الدورات"
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
            <CourseCategoryForm roots={roots} defaultParentId={defaultParentId} />
        </AdminUiPage>
    );
}
