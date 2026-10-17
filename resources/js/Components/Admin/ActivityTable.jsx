import Pagination from '@/Components/Admin/Pagination';
import useCan from '@/hooks/useCan';
import { Link } from '@inertiajs/react';

/**
 * Arabic labels for the machine `event` values stored in activity_log.
 *
 * The log deliberately stores language-neutral values (created/updated/login_failed…)
 * so the data stays translatable; the mapping lives here.
 */
export const EVENT_LABELS = {
    created: 'إنشاء',
    updated: 'تعديل',
    deleted: 'حذف',
    restored: 'استرجاع',
    login: 'دخول',
    logout: 'خروج',
    login_failed: 'محاولة فاشلة',
    lockout: 'حظر مؤقت',
    password_reset: 'إعادة كلمة مرور',
    email_verified: 'تأكيد بريد',
    registered: 'حساب جديد',
    roles_attached: 'إضافة أدوار',
    roles_detached: 'إزالة أدوار',
    permissions_attached: 'إضافة صلاحيات',
    permissions_detached: 'إزالة صلاحيات',
};

export const LOG_NAME_LABELS = {
    users: 'المستخدمون',
    settings: 'الإعدادات',
    content: 'المحتوى',
    backups: 'النسخ الاحتياطي',
    whatsapp: 'واتساب',
    security: 'الأمن',
    default: 'عام',
};

const EVENT_TONES = {
    created: 'admin-badge-success',
    updated: 'admin-badge-accent',
    deleted: 'admin-badge-danger',
    login: 'admin-badge-success',
    logout: 'admin-badge-muted',
    login_failed: 'admin-badge-warning',
    lockout: 'admin-badge-danger',
    roles_attached: 'admin-badge-warning',
    roles_detached: 'admin-badge-warning',
    permissions_attached: 'admin-badge-warning',
    permissions_detached: 'admin-badge-warning',
};

export function eventLabel(event) {
    return EVENT_LABELS[event] || event || '—';
}

export function eventTone(event) {
    return EVENT_TONES[event] || 'admin-badge-accent';
}

export function logNameLabel(logName) {
    return LOG_NAME_LABELS[logName] || logName || '—';
}

/**
 * Shared table for the audit and security timelines. `showLogName` is off on the
 * security screen, where every row is log_name=security anyway.
 */
export default function ActivityTable({ items, showLogName = true, emptyMessage = 'لا توجد سجلات.' }) {
    // The details page needs the activity permission, even when this table lists security events.
    const { can } = useCan();
    const canOpen = can('admin.observability.activity');
    const rows = items?.data || [];

    return (
        <>
            <div className="admin-table-wrap">
                <table className="admin-data-table">
                    <thead>
                        <tr>
                            <th>الحدث</th>
                            {showLogName && <th>الفئة</th>}
                            <th>العنصر</th>
                            <th>المستخدم</th>
                            <th>IP</th>
                            <th>التاريخ</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <td>
                                    <span className={`admin-badge ${eventTone(row.event)}`}>
                                        {eventLabel(row.event)}
                                    </span>
                                </td>
                                {showLogName && <td>{logNameLabel(row.log_name)}</td>}
                                <td>
                                    {row.subject_label ? (
                                        <span>
                                            {row.subject_label}
                                            {row.subject_id ? (
                                                <span className="text-muted" dir="ltr">
                                                    {' '}
                                                    #{row.subject_id}
                                                </span>
                                            ) : null}
                                        </span>
                                    ) : (
                                        <span className="text-muted">—</span>
                                    )}
                                </td>
                                <td>
                                    {row.causer ? (
                                        <span title={row.causer.email}>{row.causer.name}</span>
                                    ) : (
                                        <span className="text-muted">النظام</span>
                                    )}
                                </td>
                                <td dir="ltr">{row.ip || <span className="text-muted">—</span>}</td>
                                <td>
                                    <span className="d-block" dir="ltr">
                                        {row.created_at}
                                    </span>
                                    <span className="text-muted fs-11">{row.created_at_human}</span>
                                </td>
                                <td>
                                    {canOpen && (
                                        <Link
                                            href={route('admin.observability.show', row.id)}
                                            className="admin-action-btn admin-action-edit"
                                            title="التفاصيل"
                                        >
                                            <i className="ri-eye-line" />
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={showLogName ? 7 : 6} className="text-center text-muted py-4">
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={items} />
        </>
    );
}
