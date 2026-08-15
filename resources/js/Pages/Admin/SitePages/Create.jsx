import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import SitePageForm from '@/Components/Admin/SitePageForm';
import { Link, useForm } from '@inertiajs/react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        slug_locked: false,
        excerpt: '',
        body: '',
        meta_title: '',
        meta_description: '',
        is_published: false,
        sort_order: 0,
    });

    return (
        <AdminUiPage
            title="إنشاء صفحة"
            subtitle="أضف صفحة محتوى جديدة للموقع"
            actions={
                <Link href={route('admin.site-pages.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <AdminPanel title="بيانات الصفحة" icon="ri-file-add-line">
                <SitePageForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    submitLabel="إنشاء الصفحة"
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(route('admin.site-pages.store'));
                    }}
                />
            </AdminPanel>
        </AdminUiPage>
    );
}
