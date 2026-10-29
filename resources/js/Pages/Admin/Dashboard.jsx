import AdminUiPage from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link, usePage } from '@inertiajs/react';

const shortcuts = [
    {
        title: 'الدورات',
        desc: 'إدارة الدورات والمناهج',
        icon: 'ri-play-list-2-line',
        color: 'cyan',
        href: 'admin.courses.index',
        permission: 'admin.courses.list',
    },
    {
        title: 'طلبات الانضمام',
        desc: 'مراجعة وقبول الطلاب',
        icon: 'ri-user-follow-line',
        color: 'teal',
        href: 'admin.enrollments.index',
        permission: 'admin.enrollments.list',
    },
    {
        title: 'تصنيفات الدورات',
        desc: 'المجالات وترتيبها',
        icon: 'ri-folder-3-line',
        color: 'indigo',
        href: 'admin.course-categories.index',
        permission: 'admin.course-categories.list',
    },
    {
        title: 'مقالات المدونة',
        desc: 'إدارة المقالات',
        icon: 'ri-article-line',
        color: 'pink',
        href: 'admin.blog-posts.index',
        permission: 'admin.blog-posts.list',
    },
    {
        title: 'القائمة البريدية',
        desc: 'المشتركون والتصدير',
        icon: 'ri-mail-send-line',
        color: 'gold',
        href: 'admin.newsletter.index',
        permission: 'admin.newsletter-subscribers.list',
    },
    {
        title: 'مكتبة الوسائط',
        desc: 'الصور والملفات',
        icon: 'ri-image-2-line',
        color: 'red',
        href: 'admin.media.index',
        permission: 'admin.media.list',
    },
    {
        title: 'إعدادات الموقع',
        desc: 'الاسم والشعار والتواصل',
        icon: 'ri-settings-4-line',
        color: 'brown',
        href: 'admin.site-settings.edit',
        permission: 'admin.site-settings.list',
    },
    {
        title: 'المستخدمون',
        desc: 'إدارة الحسابات',
        icon: 'ri-group-line',
        color: 'teal',
        href: 'admin.users.index',
        permission: 'admin.users.list',
    },
];

export default function Dashboard({ widgets = [], lastLoginAt }) {
    const { auth } = usePage().props;
    const { can, canAny } = useCan();
    const allowed = (permission) =>
        !permission || (Array.isArray(permission) ? canAny(permission) : can(permission));

    const visibleShortcuts = shortcuts.filter((s) => allowed(s.permission));

    return (
        <AdminUiPage
            title={`مرحباً بك، ${auth.user.name}!`}
            subtitle={lastLoginAt ? `آخر تسجيل دخول: ${lastLoginAt}` : 'نظرة عامة على لوحة التحكم'}
        >
            <div className="row g-3 mb-4">
                {widgets.map((widget, index) => (
                    <div className="col-xl-3 col-lg-6 col-md-6" key={widget.key}>
                        {(() => {
                            // Numbers show for everyone on the dashboard; the card links only
                            // when the viewer may open its page.
                            const card = (
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
                            );
                            const style = { '--card-delay': `${index * 0.1}s` };

                            return widget.route && canAny(widget.permissions ?? []) ? (
                                <Link
                                    href={route(widget.route)}
                                    className="dashboard-stat-link"
                                    style={style}
                                >
                                    {card}
                                </Link>
                            ) : (
                                <div className="dashboard-stat-link" style={style}>
                                    {card}
                                </div>
                            );
                        })()}
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
                        {visibleShortcuts.map((shortcut, index) => {
                            const Tag = shortcut.url ? 'a' : Link;
                            const linkProps = shortcut.url
                                ? { href: shortcut.url, target: '_blank', rel: 'noopener' }
                                : { href: route(shortcut.href) };

                            return (
                                <div
                                    className="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6"
                                    key={shortcut.title}
                                >
                                    <Tag
                                        {...linkProps}
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
                                            <i
                                                className={
                                                    shortcut.url
                                                        ? 'ri-external-link-line'
                                                        : 'ri-arrow-left-s-line'
                                                }
                                            />
                                        </span>
                                    </Tag>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </AdminUiPage>
    );
}
