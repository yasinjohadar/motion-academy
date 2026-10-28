import AdminUiPage from '@/Components/Admin/AdminUiPage';
import TeamMemberForm, { teamMemberFormData } from '@/Components/Admin/TeamMemberForm';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ member, platforms }) {
    const { data, setData, post, processing, errors } = useForm({
        ...teamMemberFormData(member),
        _method: 'put',
    });

    return (
        <AdminUiPage
            title="تعديل العضو"
            subtitle={member.name}
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
                submitLabel="حفظ التعديلات"
                onSubmit={(e) => {
                    e.preventDefault();
                    // POST + _method so an uploaded avatar travels as multipart.
                    post(route('admin.team-members.update', member.id), {
                        forceFormData: data.avatar instanceof File,
                    });
                }}
            />
        </AdminUiPage>
    );
}
