import { eventLabel, eventTone, logNameLabel } from '@/Components/Admin/ActivityTable';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link } from '@inertiajs/react';

function ChangesTable({ changes }) {
    if (!changes?.length) {
        return <p className="text-muted mb-0">لا توجد تغييرات حقول في هذا السجل.</p>;
    }

    return (
        <div className="admin-table-wrap">
            <table className="admin-data-table">
                <thead>
                    <tr>
                        <th>الحقل</th>
                        <th>القيمة السابقة</th>
                        <th>القيمة الجديدة</th>
                    </tr>
                </thead>
                <tbody>
                    {changes.map((change) => (
                        <tr key={change.field}>
                            <td>
                                <code dir="ltr">{change.field}</code>
                            </td>
                            <td className="text-danger">
                                {change.old ?? <span className="text-muted">—</span>}
                            </td>
                            <td className="text-success">
                                {change.new ?? <span className="text-muted">—</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Show({ item, batch = [] }) {
    const isSecurity = item.log_name === 'security';

    return (
        <AdminUiPage
            title="تفاصيل السجل"
            subtitle={item.description}
            flash={null}
            actions={
                <Link
                    href={
                        isSecurity
                            ? route('admin.observability.security')
                            : route('admin.observability.activity')
                    }
                    className="admin-btn admin-btn-ghost"
                >
                    <i className="ri-arrow-right-line" />
                    رجوع للسجل
                </Link>
            }
        >
            <AdminPanel title="معلومات السجل" icon="ri-information-line">
                <div className="row g-3">
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">الحدث</div>
                        <span className={`admin-badge ${eventTone(item.event)}`}>
                            {eventLabel(item.event)}
                        </span>
                    </div>
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">الفئة</div>
                        <strong>{logNameLabel(item.log_name)}</strong>
                    </div>
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">التاريخ</div>
                        <strong dir="ltr">{item.created_at}</strong>
                        <span className="text-muted fs-12"> ({item.created_at_human})</span>
                    </div>
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">المستخدم</div>
                        {item.causer ? (
                            <strong>
                                {item.causer.name}
                                <span className="text-muted fs-12" dir="ltr">
                                    {' '}
                                    ({item.causer.email})
                                </span>
                            </strong>
                        ) : (
                            <strong className="text-muted">النظام (بدون مستخدم)</strong>
                        )}
                    </div>
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">العنصر</div>
                        <strong>
                            {item.subject_label || '—'}
                            {item.subject_id ? (
                                <span className="text-muted" dir="ltr">
                                    {' '}
                                    #{item.subject_id}
                                </span>
                            ) : null}
                        </strong>
                    </div>
                    <div className="col-12 col-md-6 col-lg-4">
                        <div className="text-muted fs-12">عنوان IP</div>
                        <strong dir="ltr">{item.ip || '—'}</strong>
                    </div>
                    {item.user_agent && (
                        <div className="col-12">
                            <div className="text-muted fs-12">متصفح / جهاز</div>
                            <span dir="ltr" className="fs-12">
                                {item.user_agent}
                            </span>
                        </div>
                    )}
                </div>
            </AdminPanel>

            <AdminPanel title="التغييرات" icon="ri-file-transfer-line">
                <ChangesTable changes={item.changes} />
            </AdminPanel>

            {item.extra?.length > 0 && (
                <AdminPanel title="خصائص إضافية" icon="ri-list-settings-line">
                    <div className="admin-table-wrap">
                        <table className="admin-data-table">
                            <tbody>
                                {item.extra.map((entry) => (
                                    <tr key={entry.key}>
                                        <td style={{ width: '30%' }}>
                                            <code dir="ltr">{entry.key}</code>
                                        </td>
                                        <td dir="auto">{entry.value}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            )}

            {batch.length > 0 && (
                <AdminPanel title="تغييرات مرتبطة (نفس العملية)" icon="ri-stack-line">
                    <p className="text-muted fs-12">
                        هذه السجلات نُفّذت في نفس العملية، لذلك تُقرأ كتغيير واحد.
                    </p>
                    <div className="admin-table-wrap">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الحدث</th>
                                    <th>العنصر</th>
                                    <th>التاريخ</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {batch.map((row) => (
                                    <tr key={row.id}>
                                        <td>
                                            <span className={`admin-badge ${eventTone(row.event)}`}>
                                                {eventLabel(row.event)}
                                            </span>
                                        </td>
                                        <td>
                                            {row.subject_label} #{row.subject_id}
                                        </td>
                                        <td dir="ltr">{row.created_at}</td>
                                        <td>
                                            <Link
                                                href={route('admin.observability.show', row.id)}
                                                className="admin-action-btn admin-action-edit"
                                                title="التفاصيل"
                                            >
                                                <i className="ri-eye-line" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            )}
        </AdminUiPage>
    );
}
