import PermissionPicker from '@/Components/Admin/PermissionPicker';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ permissionGroups }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        permissions: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.roles.store'));
    };

    return (
        <AdminUiPage
            title="إنشاء دور جديد"
            subtitle="حدد اسم الدور والصلاحيات المرتبطة به"
            actions={
                <Link href={route('admin.roles.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات الدور" icon="ri-shield-user-line">
                <form onSubmit={submit} className="row g-3">
                    <div className="col-md-6">
                        <label className="form-label">اسم الدور</label>
                        <input
                            type="text"
                            className={`form-control${errors.name ? ' is-invalid' : ''}`}
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="مثال: content-manager"
                            dir="ltr"
                        />
                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                        <div className="form-text">حروف إنجليزية وأرقام وشرطات فقط</div>
                    </div>

                    <div className="col-12">
                        <label className="form-label mb-3">الصلاحيات</label>
                        <PermissionPicker
                            groups={permissionGroups}
                            selected={data.permissions}
                            onChange={(permissions) => setData('permissions', permissions)}
                            error={errors.permissions}
                        />
                    </div>

                    <div className="col-12 d-flex gap-2">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing}
                        >
                            <i className="ri-save-line" />
                            حفظ
                        </button>
                        <Link href={route('admin.roles.index')} className="admin-btn admin-btn-ghost">
                            إلغاء
                        </Link>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
