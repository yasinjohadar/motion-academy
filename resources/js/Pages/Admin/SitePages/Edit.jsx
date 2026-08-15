import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import SitePageForm from '@/Components/Admin/SitePageForm';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ page }) {
    const { data, setData, put, processing, errors } = useForm({
        title: page.title || '',
        slug: page.slug || '',
        slug_locked: true,
        excerpt: page.excerpt || '',
        body: page.body || '',
        meta_title: page.meta_title || '',
        meta_description: page.meta_description || '',
        is_published: Boolean(page.is_published),
        sort_order: page.sort_order ?? 0,
    });

    return (
        <AdminUiPage
            title="تعديل الصفحة"
            subtitle={page.title}
            actions={
                <Link href={route('admin.site-pages.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <AdminPanel title="بيانات الصفحة" icon="ri-file-edit-line">
                <SitePageForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    submitLabel="حفظ التعديلات"
                    onSubmit={(e) => {
                        e.preventDefault();
                        put(route('admin.site-pages.update', page.id));
                    }}
                />
            </AdminPanel>
        </AdminUiPage>
    );
}
