import AdminUiPage from '@/Components/Admin/AdminUiPage';
import TeamMemberForm, { teamMemberFormData } from '@/Components/Admin/TeamMemberForm';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ platforms, nextSortOrder }) {
    const { data, setData, post, processing, errors } = useForm(
        teamMemberFormData(null, { sort_order: nextSortOrder }),
    );

    return (
        <AdminUiPage
            title="عضو جديد"
            subtitle="أضف خبيراً إلى قسم الفريق"
            actions={
                <Link href={route('admin.team-members.index')} className="admin-btn admin-btn-ghost">
                    العودة
                </Link>
            }
        >
            <TeamMemberForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                platforms={platforms}
                submitLabel="إضافة العضو"
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.team-members.store'), {
                        forceFormData: data.avatar instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
