import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import AccountTypeField from '@/Components/Admin/AccountTypeField';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ accountTypes = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        name_ar: '',
        name_en: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        account_type: 'student',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.users.store'));
    };

    return (
        <AdminUiPage
            title="إنشاء مستخدم جديد"
            subtitle="أدخل الاسم بالعربي والإنكليزي مع رقم الهاتف"
            actions={
                <Link href={route('admin.users.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة للقائمة
                </Link>
            }
        >
            <AdminPanel title="بيانات المستخدم" icon="ri-user-add-line">
                <form onSubmit={submit} className="row g-3">
                    <div className="col-md-6">
                        <label className="form-label">الاسم بالعربي</label>
                        <input
                            type="text"
                            className={`form-control${errors.name_ar ? ' is-invalid' : ''}`}
                            value={data.name_ar}
                            onChange={(e) => setData('name_ar', e.target.value)}
                        />
                        {errors.name_ar && <div className="invalid-feedback">{errors.name_ar}</div>}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">الاسم بالإنكليزي</label>
                        <input
                            type="text"
                            className={`form-control${errors.name_en ? ' is-invalid' : ''}`}
                            value={data.name_en}
                            onChange={(e) => setData('name_en', e.target.value)}
                            dir="ltr"
                        />
                        {errors.name_en && <div className="invalid-feedback">{errors.name_en}</div>}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">البريد الإلكتروني</label>
                        <input
                            type="email"
                            className={`form-control${errors.email ? ' is-invalid' : ''}`}
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            dir="ltr"
                        />
                        {errors.email && <div className="invalid-feedback">{errors.email}</div>}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">رقم الهاتف</label>
                        <input
                            type="text"
                            className={`form-control${errors.phone ? ' is-invalid' : ''}`}
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="05xxxxxxxx"
                            dir="ltr"
                        />
                        {errors.phone && <div className="invalid-feedback">{errors.phone}</div>}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">كلمة المرور</label>
                        <input
                            type="password"
                            className={`form-control${errors.password ? ' is-invalid' : ''}`}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            dir="ltr"
                        />
                        {errors.password && <div className="invalid-feedback">{errors.password}</div>}
                    </div>

                    <div className="col-md-6">
                        <label className="form-label">تأكيد كلمة المرور</label>
                        <input
                            type="password"
                            className="form-control"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            dir="ltr"
                        />
                    </div>

                    <div className="col-12">
                        <AccountTypeField
                            value={data.account_type}
                            onChange={(value) => setData('account_type', value)}
                            types={accountTypes}
                            error={errors.account_type}
                        />
                    </div>

                    <div className="col-12 d-flex gap-2 mt-3">
                        <button type="submit" className="admin-btn admin-btn-primary" disabled={processing}>
                            <i className="ri-save-line" />
                            حفظ
                        </button>
                        <Link href={route('admin.users.index')} className="admin-btn admin-btn-ghost">
                            إلغاء
                        </Link>
                    </div>
                </form>
            </AdminPanel>
        </AdminUiPage>
    );
}
