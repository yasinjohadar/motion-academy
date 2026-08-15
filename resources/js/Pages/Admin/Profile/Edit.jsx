import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AdminUiPage title="الملف الشخصي" subtitle="إدارة معلومات حسابك">
            <div className="row g-3">
                <div className="col-xl-12">
                    <AdminPanel title="معلومات الملف الشخصي" icon="ri-user-line">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </AdminPanel>
                </div>

                <div className="col-xl-12">
                    <AdminPanel title="تحديث كلمة المرور" icon="ri-lock-password-line">
                        <UpdatePasswordForm />
                    </AdminPanel>
                </div>

                <div className="col-xl-12">
                    <AdminPanel title="حذف الحساب" icon="ri-delete-bin-line">
                        <DeleteUserForm />
                    </AdminPanel>
                </div>
            </div>
        </AdminUiPage>
    );
}
