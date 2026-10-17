import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import useCan from '@/hooks/useCan';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ instances, settings, rotationPoolCount, flash }) {
    const { can } = useCan();
    const [toDelete, setToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const form = useForm({
        instance_name: '',
        label: '',
        set_as_default: false,
        create_remote: true,
    });

    const addInstance = (e) => {
        e.preventDefault();
        form.post(route('admin.whatsapp-instances.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('instance_name', 'label'),
        });
    };

    return (
        <WhatsAppPage
            title="أرقام واتساب"
            subtitle="إدارة الـ instances والتبديل بين الأرقام لحماية الحساب من الحظر."
            chips={[
                { icon: 'ri-smartphone-line', label: 'نشطة للتبديل', value: rotationPoolCount },
                {
                    icon: 'ri-refresh-line',
                    label: settings.evolution_rotation_enabled ? 'التبديل مفعّل' : 'التبديل معطّل',
                },
            ]}
            flash={flash}
            actions={
                can('admin.whatsapp-instances.edit') ? (
                    <button
                        type="button"
                        className="wa-btn wa-btn-primary"
                        onClick={() =>
                            router.post(route('admin.whatsapp-instances.sync'), {}, { preserveScroll: true })
                        }
                    >
                        <i className="ri-refresh-line" />
                        مزامنة
                    </button>
                ) : null
            }
        >
            <DeleteConfirmModal
                show={Boolean(toDelete)}
                message={toDelete ? `حذف الرقم «${toDelete}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    setDeleting(true);
                    router.delete(route('admin.whatsapp-instances.destroy', toDelete), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setToDelete(null)}
            />

            {can('admin.whatsapp-instances.create') && (
                <WaPanel title="إضافة رقم" icon="ri-add-circle-line">
                    <form onSubmit={addInstance}>
                        <div className="row g-3 align-items-end">
                            <div className="col-md-3">
                                <label className="form-label">اسم Instance</label>
                                <input
                                    className="form-control"
                                    value={form.data.instance_name}
                                    onChange={(e) => form.setData('instance_name', e.target.value)}
                                    dir="ltr"
                                    required
                                />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">تسمية</label>
                                <input
                                    className="form-control"
                                    value={form.data.label}
                                    onChange={(e) => form.setData('label', e.target.value)}
                                />
                            </div>
                            <div className="col-md-4 d-flex flex-wrap gap-3 align-items-center pb-2">
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={form.data.create_remote}
                                        onChange={(e) => form.setData('create_remote', e.target.checked)}
                                    />
                                    <span className="form-check-label">إنشاء على Evolution</span>
                                </label>
                                <label className="form-check mb-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={form.data.set_as_default}
                                        onChange={(e) => form.setData('set_as_default', e.target.checked)}
                                    />
                                    <span className="form-check-label">افتراضي</span>
                                </label>
                            </div>
                            <div className="col-md-2">
                                <button className="wa-btn wa-btn-ink w-100" disabled={form.processing}>
                                    <i className="ri-add-line" />
                                    إضافة
                                </button>
                            </div>
                        </div>
                    </form>
                </WaPanel>
            )}

            <WaPanel title="الأرقام المسجّلة" icon="ri-list-check-2">
                <div className="wa-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>الهاتف</th>
                                <th>الحالة</th>
                                <th>التبديل</th>
                                <th>آخر استخدام</th>
                                <th>عمليات</th>
                            </tr>
                        </thead>
                        <tbody>
                            {instances.length === 0 && (
                                <TableEmptyRow colSpan={6}>لا توجد أرقام بعد.</TableEmptyRow>
                            )}
                            {instances.map((row) => (
                                <tr key={row.id}>
                                    <td>
                                        <strong dir="ltr">{row.instance_name}</strong>
                                        {row.label && <div className="small text-muted">{row.label}</div>}
                                        {row.is_default && (
                                            <span className="wa-badge wa-badge-brass ms-1">افتراضي</span>
                                        )}
                                    </td>
                                    <td dir="ltr">{row.phone_number || '—'}</td>
                                    <td>
                                        <span
                                            className={`wa-badge ${
                                                row.connection_status === 'open'
                                                    ? 'wa-badge-ok'
                                                    : 'wa-badge-muted'
                                            }`}
                                        >
                                            {row.connection_status}
                                        </span>
                                    </td>
                                    <td>{row.rotation_enabled ? 'مفعّل' : 'معطّل'}</td>
                                    <td dir="ltr">{row.last_used_at || '—'}</td>
                                    <td>
                                        <div className="d-flex flex-wrap gap-1">
                                            {can('admin.whatsapp-instances.edit') && (
                                                <Link
                                                    href={route(
                                                        'admin.whatsapp-instances.connect',
                                                        row.instance_name,
                                                    )}
                                                    className="wa-btn wa-btn-ghost wa-btn-sm"
                                                >
                                                    QR
                                                </Link>
                                            )}
                                            {can('admin.whatsapp-instances.edit') && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="wa-btn wa-btn-ghost wa-btn-sm"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'admin.whatsapp-instances.toggle-rotation',
                                                                    row.instance_name,
                                                                ),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        تبديل
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="wa-btn wa-btn-ghost wa-btn-sm"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'admin.whatsapp-instances.set-default',
                                                                    row.instance_name,
                                                                ),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        افتراضي
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="wa-btn wa-btn-ghost wa-btn-sm"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'admin.whatsapp-instances.refresh',
                                                                    row.instance_name,
                                                                ),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        تحديث
                                                    </button>
                                                </>
                                            )}
                                            {can('admin.whatsapp-instances.delete') && (
                                                <button
                                                    type="button"
                                                    className="wa-btn wa-btn-danger wa-btn-sm"
                                                    onClick={() => setToDelete(row.instance_name)}
                                                >
                                                    حذف
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </WaPanel>
        </WhatsAppPage>
    );
}
