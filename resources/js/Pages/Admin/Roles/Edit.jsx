import PermissionPicker from '@/Components/Admin/PermissionPicker';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, useForm } from '@inertiajs/react';

export default function Edit({ role, permissionGroups }) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name || '',
        permissions: role.permissions || [],
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.roles.update', role.id));
    };

    return (
        <AdminUiPage
            title="تعديل الدور"
            subtitle={role.name}
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
                            disabled={role.is_locked}
                            dir="ltr"
                        />
                        {errors.name && <div className="invalid-feedback">{errors.name}</div>}
                        {role.is_locked && (
                            <div className="form-text">دور المدير الأعلى محمي من إعادة التسمية</div>
                        )}
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
                            تحديث
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
