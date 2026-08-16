import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage from '@/Components/Admin/AdminUiPage';
import { eventLabel, eventTone } from '@/Components/Admin/ActivityTable';
import useCan from '@/hooks/useCan';
import { Link, usePage } from '@inertiajs/react';

const shortcuts = [
    {
        title: 'المراقبة والسجلات',
        desc: 'نشاط النظام',
        icon: 'ri-radar-line',
        color: 'blue',
        href: 'admin.observability.index',
        permission: 'admin.observability.activity',
    },
    {
        title: 'المستخدمون',
        desc: 'إدارة الحسابات',
        icon: 'ri-group-line',
        color: 'teal',
        href: 'admin.users.index',
        permission: 'admin.users.list',
    },
    {
        title: 'الملف الشخصي',
        desc: 'تعديل الحساب',
        icon: 'ri-user-settings-line',
        color: 'purple',
        href: 'admin.profile.edit',
        permission: null,
    },
];

/**
 * Dependency-free bar chart. The vendored theme ships ApexCharts, but none of it is
 * loaded in the layout — pulling a charting library into every page load for one
 * 14-bar sparkline is not worth it.
 */
function ActivityChart({ data }) {
    const max = Math.max(1, ...data.map((point) => point.total));
    const hasAny = data.some((point) => point.total > 0);

    return (
        <>
            <div
                className="d-flex align-items-end gap-1 gap-sm-2"
                style={{ height: 180 }}
                role="img"
                aria-label="نشاط النظام خلال آخر 14 يوماً"
            >
                {data.map((point) => (
                    <div
                        key={point.date}
                        className="d-flex flex-column align-items-center justify-content-end flex-grow-1 h-100"
                        title={`${point.label} — ${point.total} حدث`}
                    >
                        <span className="fs-11 text-muted mb-1" dir="ltr">
                            {point.total > 0 ? point.total : ''}
                        </span>
                        <div
                            style={{
                                width: '100%',
                                height: `${(point.total / max) * 100}%`,
                                minHeight: point.total > 0 ? 4 : 2,
                                borderRadius: '6px 6px 0 0',
                                background:
                                    point.total > 0
                                        ? 'linear-gradient(180deg, var(--primary-color, #0162e8), rgba(1,98,232,.45))'
                                        : 'var(--default-border, rgba(0,0,0,.08))',
                                transition: 'height .3s ease',
                            }}
                        />
                        <span className="fs-11 text-muted mt-1" dir="ltr">
                            {point.label}
                        </span>
                    </div>
                ))}
            </div>
            {!hasAny && (
                <p className="text-muted fs-12 text-center mt-3 mb-0">
                    لا يوجد نشاط مسجّل خلال آخر 14 يوماً.
                </p>
            )}
        </>
    );
}

export default function Dashboard({
    widgets = [],
    health,
    todaySummary = [],
    activityChart = [],
    recentActivity = [],
    lastLoginAt,
}) {
    const { auth } = usePage().props;
    const { can } = useCan();

    const visibleShortcuts = shortcuts.filter((s) => !s.permission || can(s.permission));

    return (
        <AdminUiPage
            title={`مرحباً بك، ${auth.user.name}!`}
            subtitle={
                lastLoginAt
                    ? `آخر تسجيل دخول: ${lastLoginAt}`
                    : 'نظرة عامة على لوحة التحكم'
            }
        >
            <div className="row g-3 mb-4">
                {widgets.map((widget, index) => (
                    <div className="col-xl-3 col-lg-6 col-md-6" key={widget.key}>
                        <Link
                            href={route(widget.route)}
                            className="dashboard-stat-link"
                            style={{ '--card-delay': `${index * 0.1}s` }}
                        >
                            <div className={`dashboard-stat-card dashboard-stat-${widget.theme}`}>
                                <div className="stat-card-shine" />
                                <div className="stat-card-mesh" />
                                <div className="stat-card-bubble stat-card-bubble-1" />
                                <div className="stat-card-bubble stat-card-bubble-2" />
                                <div className="stat-card-bubble stat-card-bubble-3" />
                                <div className="stat-card-glow" />
                                <div className="stat-card-body">
                                    <div className="stat-card-content">
                                        <span className="stat-label">{widget.title}</span>
                                        <span className="stat-value" dir="ltr">
                                            {Number(widget.value).toLocaleString('en-US')}
                                        </span>
                                        <span className="stat-subtext">{widget.subtext}</span>
                                    </div>
                                    <div className="stat-icon-wrap">
                                        <span className="stat-icon-ring" />
                                        <span className="stat-icon-circle">
                                            <i className={widget.icon} />
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                ))}
            </div>

            {visibleShortcuts.length > 0 && (
                <div className="shortcuts-section mb-4">
                    <div className="shortcuts-section-header">
                        <span className="shortcuts-section-icon">
                            <i className="ri-flashlight-line" />
                        </span>
                        <h5 className="dashboard-section-title mb-0">اختصارات سريعة</h5>
                    </div>
                    <div className="row g-3 shortcuts-grid">
                        {visibleShortcuts.map((shortcut, index) => (
                            <div
                                className="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6"
                                key={shortcut.title}
                            >
                                <Link
                                    href={route(shortcut.href)}
                                    className={`shortcut-card shortcut-theme-${shortcut.color}`}
                                    style={{ '--shortcut-delay': `${index * 0.05}s` }}
                                >
                                    <span className="shortcut-shine" />
                                    <span className="shortcut-accent" />
                                    <span className="shortcut-icon-wrap">
                                        <span className="shortcut-icon-ring" />
                                        <span className="shortcut-icon">
                                            <i className={shortcut.icon} />
                                        </span>
                                    </span>
                                    <span className="shortcut-title">{shortcut.title}</span>
                                    <span className="shortcut-desc">{shortcut.desc}</span>
                                    <span className="shortcut-arrow">
                                        <i className="ri-arrow-left-s-line" />
                                    </span>
                                </Link>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="row g-3 mb-4">
                <div className="col-xl-8 col-lg-7">
                    <div className="card custom-card h-100">
                        <div className="card-header d-flex align-items-center justify-content-between">
                            <h6 className="card-title mb-0">نشاط النظام</h6>
                            <span className="text-muted fs-12">آخر 14 يوماً</span>
                        </div>
                        <div className="card-body">
                            <ActivityChart data={activityChart} />
                        </div>
                    </div>
                </div>

                <div className="col-xl-4 col-lg-5">
                    <div className="card custom-card h-100">
                        <div className="card-header">
                            <h6 className="card-title mb-0">ملخص اليوم</h6>
                        </div>
                        <div className="card-body pt-2">
                            {todaySummary.map((item) => (
                                <div className="today-summary-item" key={item.label}>
                                    <div className="d-flex align-items-center">
                                        <span
                                            className={`summary-icon menu-icon-box menu-icon-${item.color} menu-icon-sm`}
                                        >
                                            <i className={item.icon} />
                                        </span>
                                        <span className="summary-label">{item.label}</span>
                                    </div>
                                    <span className="summary-value" dir="ltr">
                                        {item.value}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {health?.checks?.length > 0 && (
                <div className="row g-3 mb-4">
                    <div className="col-12">
                        <div className="card custom-card">
                            <div className="card-header d-flex align-items-center justify-content-between">
                                <h6 className="card-title mb-0">فحوصات النظام</h6>
                                <span
                                    className={`admin-badge ${
                                        health.score === 100
                                            ? 'admin-badge-success'
                                            : 'admin-badge-warning'
                                    }`}
                                >
                                    {health.score}%
                                </span>
                            </div>
                            <div className="card-body">
                                <div className="d-flex flex-wrap gap-2">
                                    {health.checks.map((check) => (
                                        <span
                                            key={check.label}
                                            className={`admin-badge ${
                                                check.passed
                                                    ? 'admin-badge-success'
                                                    : 'admin-badge-danger'
                                            }`}
                                        >
                                            <i
                                                className={
                                                    check.passed
                                                        ? 'ri-check-line'
                                                        : 'ri-close-line'
                                                }
                                            />{' '}
                                            {check.label}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="row g-3">
                <div className="col-xl-12">
                    <div className="dashboard-table-card" style={{ '--table-delay': '0.1s' }}>
                        <div className="dashboard-table-header">
                            <div className="dashboard-table-header-main">
                                <span className="dashboard-table-header-icon icon-students">
                                    <i className="ri-history-line" />
                                </span>
                                <div>
                                    <h3>آخر النشاطات</h3>
                                    <p>أحدث التغييرات المسجّلة في النظام</p>
                                </div>
                            </div>
                            {can('admin.observability.activity') && (
                                <Link
                                    href={route('admin.observability.activity')}
                                    className="admin-btn admin-btn-ghost"
                                >
                                    عرض الكل
                                </Link>
                            )}
                        </div>
                        <div className="admin-table-wrap">
                            <div className="table-responsive">
                                <table className="admin-data-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>المستخدم</th>
                                            <th>النشاط</th>
                                            <th>العنصر</th>
                                            <th>التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentActivity.map((row, index) => (
                                            <tr key={row.id} style={{ '--row-index': index }}>
                                                <td>
                                                    <div className="dashboard-student-cell">
                                                        <span className="dashboard-row-avatar">
                                                            {(row.causer_name || 'ن').charAt(0)}
                                                        </span>
                                                        <div>
                                                            <h6>{row.causer_name || 'النظام'}</h6>
                                                            <p className="student-code" dir="ltr">
                                                                {row.causer_email || '—'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span
                                                        className={`admin-badge ${eventTone(row.event)}`}
                                                    >
                                                        {eventLabel(row.event)}
                                                    </span>
                                                </td>
                                                <td>{row.subject_label || '—'}</td>
                                                <td>
                                                    <span className="d-block" dir="ltr">
                                                        {row.created_at}
                                                    </span>
                                                    <span className="text-muted fs-11">
                                                        {row.created_at_human}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                        {recentActivity.length === 0 && (
                                            <TableEmptyRow colSpan={4}>
                                                لا توجد نشاطات مسجّلة بعد.
                                            </TableEmptyRow>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminUiPage>
    );
}
