import useCan from '@/hooks/useCan';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Sidebar() {
    const { can, canAny } = useCan();
    const { site, notifications } = usePage().props;
    const siteName = site?.site_name || 'لوحة التحكم';
    const siteInitial = siteName.trim().charAt(0) || 'ل';
    const unreadCount = notifications?.unread_count ?? 0;
    const isDashboard = route().current('admin.dashboard');
    const isNotifications = route().current('admin.notifications.*');
    const isObservability = route().current('admin.observability.*');
    const isUsers = route().current('admin.users.*');
    const isNewsletter = route().current('admin.newsletter.*');
    const isEnrollments = route().current('admin.enrollments.*');
    const isRoles = route().current('admin.roles.*');
    const isSmtp = route().current('admin.smtp-settings.*');
    const isAiProviders = route().current('admin.ai-providers.*');
    const isOtpSettings = route().current('admin.otp-settings.*');
    const isSiteSettings = route().current('admin.site-settings.*');
    const isSitePages = route().current('admin.site-pages.*');
    const isAppStorage = route().current('admin.app-storage.*');
    const isDiskMappings = route().current('admin.storage-disk-mappings.*');
    const isBackups = route().current('admin.backups.*');
    const isBackupSchedules = route().current('admin.backup-schedules.*');
    const isSettings =
        isNotifications ||
        isObservability ||
        isSmtp ||
        isOtpSettings ||
        isAiProviders ||
        isSiteSettings ||
        isSitePages ||
        isAppStorage ||
        isDiskMappings ||
        isBackups ||
        isBackupSchedules;
    const isProfile = route().current('admin.profile.edit');
    const isMedia = route().current('admin.media.*');
    const isWhatsApp =
        route().current('admin.whatsapp-settings.*') ||
        route().current('admin.whatsapp-instances.*') ||
        route().current('admin.whatsapp-send.*') ||
        route().current('admin.whatsapp-campaigns.*') ||
        route().current('admin.whatsapp-messages.*');
    const isBlogPosts = route().current('admin.blog-posts.*');
    const isBlogCategories = route().current('admin.blog-categories.*');
    const isBlogTags = route().current('admin.blog-tags.*');
    const isBlogAi = route().current('admin.blog-ai.*');
    const isBlog = isBlogPosts || isBlogCategories || isBlogTags || isBlogAi;
    const isCourseCategories = route().current('admin.course-categories.*');
    const isCourseList = route().current('admin.courses.*');
    const isCourses = isCourseCategories || isCourseList || isEnrollments;

    const [whatsAppOpen, setWhatsAppOpen] = useState(isWhatsApp);
    const [settingsOpen, setSettingsOpen] = useState(isSettings);
    const [blogOpen, setBlogOpen] = useState(isBlog);
    const [coursesOpen, setCoursesOpen] = useState(isCourses);

    useEffect(() => {
        if (isWhatsApp) {
            setWhatsAppOpen(true);
        }
    }, [isWhatsApp]);

    useEffect(() => {
        if (isSettings) {
            setSettingsOpen(true);
        }
    }, [isSettings]);

    useEffect(() => {
        if (isBlog) {
            setBlogOpen(true);
        }
    }, [isBlog]);

    useEffect(() => {
        if (isCourses) {
            setCoursesOpen(true);
        }
    }, [isCourses]);

    return (
        <aside className="app-sidebar admin-sidebar sticky" id="sidebar">
            <div className="main-sidebar-header">
                <Link
                    href={route(can('admin.dashboard.view') ? 'admin.dashboard' : 'admin.profile.edit')}
                    className="header-logo admin-sidebar-brand admin-brand-text"
                    title={siteName}
                >
                    <span className="admin-brand-text__full">{siteName}</span>
                    <span className="admin-brand-text__mark" aria-hidden="true">
                        {siteInitial}
                    </span>
                </Link>
            </div>

            <div className="main-sidebar" id="sidebar-scroll">
                <nav className="main-menu-container nav nav-pills flex-column sub-open">
                    <div className="slide-left" id="slide-left">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="#7b8191"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                        >
                            <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z" />
                        </svg>
                    </div>
                    <ul className="main-menu">
                        {can('admin.dashboard.view') && (
                            <li className={`slide${isDashboard ? ' active' : ''}`}>
                                <Link
                                    href={route('admin.dashboard')}
                                    className={`side-menu__item${isDashboard ? ' active' : ''}`}
                                >
                                    <i className="ri-home-4-line side-menu__icon" />
                                    <span className="side-menu__label">لوحة التحكم</span>
                                </Link>
                            </li>
                        )}
                        {can('admin.users.list') && (
                            <li className={`slide${isUsers ? ' active' : ''}`}>
                                <Link
                                    href={route('admin.users.index')}
                                    className={`side-menu__item${isUsers ? ' active' : ''}`}
                                >
                                    <i className="ri-group-line side-menu__icon" />
                                    <span className="side-menu__label">المستخدمون</span>
                                </Link>
                            </li>
                        )}
                        {can('admin.newsletter-subscribers.list') && (
                            <li className={`slide${isNewsletter ? ' active' : ''}`}>
                                <Link
                                    href={route('admin.newsletter.index')}
                                    className={`side-menu__item${isNewsletter ? ' active' : ''}`}
                                >
                                    <i className="ri-mail-send-line side-menu__icon" />
                                    <span className="side-menu__label">القائمة البريدية</span>
                                </Link>
                            </li>
                        )}
                        {can('admin.roles.list') && (
                            <li className={`slide${isRoles ? ' active' : ''}`}>
                                <Link
                                    href={route('admin.roles.index')}
                                    className={`side-menu__item${isRoles ? ' active' : ''}`}
                                >
                                    <i className="ri-shield-user-line side-menu__icon" />
                                    <span className="side-menu__label">الأدوار</span>
                                </Link>
                            </li>
                        )}
                        {can('admin.media.list') && (
                            <li className={`slide${isMedia ? ' active' : ''}`}>
                                <Link
                                    href={route('admin.media.index')}
                                    className={`side-menu__item${isMedia ? ' active' : ''}`}
                                >
                                    <i className="ri-gallery-line side-menu__icon" />
                                    <span className="side-menu__label">الوسائط</span>
                                </Link>
                            </li>
                        )}
                        {/* Always shown: it holds the signed-in admin's own notifications. */}
                        <li
                            className={`slide has-sub${settingsOpen ? ' open' : ''}${isSettings ? ' active' : ''}`}
                        >
                            <a
                                href="#settings-menu"
                                className={`side-menu__item${isSettings ? ' active' : ''}`}
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setSettingsOpen((open) => !open);
                                }}
                            >
                                <i className="ri-settings-4-line side-menu__icon" />
                                <span className="side-menu__label">الإعدادات</span>
                                {unreadCount > 0 && !settingsOpen && (
                                    <span className="badge bg-danger rounded-pill ms-auto">
                                        {unreadCount > 99 ? '99+' : unreadCount}
                                    </span>
                                )}
                                <i className="ri-arrow-left-s-line side-menu__angle" />
                            </a>
                            <ul
                                className="slide-menu child1"
                                style={{ display: settingsOpen ? 'block' : 'none' }}
                            >
                                {/* No can() gate — these are the signed-in user's own notifications. */}
                                <li className="slide">
                                    <Link
                                        href={route('admin.notifications.index')}
                                        className={`side-menu__item${isNotifications ? ' active' : ''}`}
                                    >
                                        <i className="ri-notification-3-line side-menu__icon" />
                                        الإشعارات
                                        {unreadCount > 0 && (
                                            <span className="badge bg-danger rounded-pill ms-auto">
                                                {unreadCount > 99 ? '99+' : unreadCount}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                                {canAny([
                                    'admin.observability.activity',
                                    'admin.observability.security',
                                    'admin.observability.logs',
                                    'admin.observability.jobs',
                                    'admin.observability.database',
                                ]) && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.observability.index')}
                                            className={`side-menu__item${isObservability ? ' active' : ''}`}
                                        >
                                            <i className="ri-radar-line side-menu__icon" />
                                            المراقبة والسجلات
                                        </Link>
                                    </li>
                                )}
                                {can('admin.site-settings.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.site-settings.edit')}
                                            className={`side-menu__item${isSiteSettings ? ' active' : ''}`}
                                        >
                                            <i className="ri-global-line side-menu__icon" />
                                            إعدادات الموقع
                                        </Link>
                                    </li>
                                )}
                                {can('admin.site-pages.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.site-pages.index')}
                                            className={`side-menu__item${isSitePages ? ' active' : ''}`}
                                        >
                                            <i className="ri-pages-line side-menu__icon" />
                                            صفحات الموقع
                                        </Link>
                                    </li>
                                )}
                                {can('admin.smtp-settings.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.smtp-settings.index')}
                                            className={`side-menu__item${isSmtp ? ' active' : ''}`}
                                        >
                                            <i className="ri-mail-settings-line side-menu__icon" />
                                            إعدادات SMTP
                                        </Link>
                                    </li>
                                )}
                                {can('admin.otp-settings.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.otp-settings.edit')}
                                            className={`side-menu__item${isOtpSettings ? ' active' : ''}`}
                                        >
                                            <i className="ri-shield-keyhole-line side-menu__icon" />
                                            رموز التحقق OTP
                                        </Link>
                                    </li>
                                )}
                                {can('admin.ai-providers.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.ai-providers.index')}
                                            className={`side-menu__item${isAiProviders ? ' active' : ''}`}
                                        >
                                            <i className="ri-robot-2-line side-menu__icon" />
                                            مزودو الذكاء الاصطناعي
                                        </Link>
                                    </li>
                                )}
                                {can('admin.app-storage.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.app-storage.index')}
                                            className={`side-menu__item${isAppStorage ? ' active' : ''}`}
                                        >
                                            <i className="ri-cloud-line side-menu__icon" />
                                            التخزين السحابي
                                        </Link>
                                    </li>
                                )}
                                {can('admin.storage-disk-mappings.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.storage-disk-mappings.index')}
                                            className={`side-menu__item${isDiskMappings ? ' active' : ''}`}
                                        >
                                            <i className="ri-hard-drive-2-line side-menu__icon" />
                                            ربط الأقراص
                                        </Link>
                                    </li>
                                )}
                                {can('admin.backups.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.backups.index')}
                                            className={`side-menu__item${isBackups ? ' active' : ''}`}
                                        >
                                            <i className="ri-database-2-line side-menu__icon" />
                                            النسخ الاحتياطية
                                        </Link>
                                    </li>
                                )}
                                {can('admin.backup-schedules.list') && (
                                    <li className="slide">
                                        <Link
                                            href={route('admin.backup-schedules.index')}
                                            className={`side-menu__item${isBackupSchedules ? ' active' : ''}`}
                                        >
                                            <i className="ri-calendar-check-line side-menu__icon" />
                                            جدولة النسخ
                                        </Link>
                                    </li>
                                )}
                            </ul>
                        </li>
                        {(can('admin.courses.list') ||
                            can('admin.course-categories.list') ||
                            can('admin.enrollments.list')) && (
                            <li
                                className={`slide has-sub${coursesOpen ? ' open' : ''}${isCourses ? ' active' : ''}`}
                            >
                                <a
                                    href="#courses-menu"
                                    className={`side-menu__item${isCourses ? ' active' : ''}`}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        setCoursesOpen((open) => !open);
                                    }}
                                >
                                    <i className="ri-graduation-cap-line side-menu__icon" />
                                    <span className="side-menu__label">الدورات</span>
                                    <i className="ri-arrow-left-s-line side-menu__angle" />
                                </a>
                                <ul
                                    className="slide-menu child1"
                                    style={{ display: coursesOpen ? 'block' : 'none' }}
                                >
                                    {can('admin.courses.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.courses.index')}
                                                className={`side-menu__item${isCourseList ? ' active' : ''}`}
                                            >
                                                <i className="ri-book-open-line side-menu__icon" />
                                                جميع الدورات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.course-categories.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.course-categories.index')}
                                                className={`side-menu__item${isCourseCategories ? ' active' : ''}`}
                                            >
                                                <i className="ri-folder-3-line side-menu__icon" />
                                                التصنيفات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.enrollments.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.enrollments.index')}
                                                className={`side-menu__item${isEnrollments ? ' active' : ''}`}
                                            >
                                                <i className="ri-user-follow-line side-menu__icon" />
                                                الانضمامات
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            </li>
                        )}
                        {(can('admin.blog-posts.list') ||
                            can('admin.blog-categories.list') ||
                            can('admin.blog-tags.list') ||
                            can('admin.blog-ai.generate')) && (
                            <li
                                className={`slide has-sub${blogOpen ? ' open' : ''}${isBlog ? ' active' : ''}`}
                            >
                                <a
                                    href="#blog-menu"
                                    className={`side-menu__item${isBlog ? ' active' : ''}`}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        setBlogOpen((open) => !open);
                                    }}
                                >
                                    <i className="ri-article-line side-menu__icon" />
                                    <span className="side-menu__label">المدونة</span>
                                    <i className="ri-arrow-left-s-line side-menu__angle" />
                                </a>
                                <ul
                                    className="slide-menu child1"
                                    style={{ display: blogOpen ? 'block' : 'none' }}
                                >
                                    {can('admin.blog-posts.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.blog-posts.index')}
                                                className={`side-menu__item${isBlogPosts ? ' active' : ''}`}
                                            >
                                                <i className="ri-article-line side-menu__icon" />
                                                المقالات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.blog-categories.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.blog-categories.index')}
                                                className={`side-menu__item${isBlogCategories ? ' active' : ''}`}
                                            >
                                                <i className="ri-folder-line side-menu__icon" />
                                                التصنيفات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.blog-tags.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.blog-tags.index')}
                                                className={`side-menu__item${isBlogTags ? ' active' : ''}`}
                                            >
                                                <i className="ri-price-tag-3-line side-menu__icon" />
                                                الوسوم
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.blog-ai.generate') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.blog-ai.create')}
                                                className={`side-menu__item${isBlogAi ? ' active' : ''}`}
                                            >
                                                <i className="ri-robot-2-line side-menu__icon" />
                                                توليد بالذكاء الاصطناعي
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            </li>
                        )}
                        {(can('admin.whatsapp-settings.list') ||
                            can('admin.whatsapp-instances.list') ||
                            can('admin.whatsapp-send.create') ||
                            can('admin.whatsapp-campaigns.list') ||
                            can('admin.whatsapp-messages.list')) && (
                            <li
                                className={`slide has-sub${whatsAppOpen ? ' open' : ''}${isWhatsApp ? ' active' : ''}`}
                            >
                                <a
                                    href="#whatsapp-menu"
                                    className={`side-menu__item${isWhatsApp ? ' active' : ''}`}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        setWhatsAppOpen((open) => !open);
                                    }}
                                >
                                    <i className="ri-whatsapp-line side-menu__icon" />
                                    <span className="side-menu__label">واتساب</span>
                                    <i className="ri-arrow-left-s-line side-menu__angle" />
                                </a>
                                <ul
                                    className="slide-menu child1"
                                    style={{ display: whatsAppOpen ? 'block' : 'none' }}
                                >
                                    {can('admin.whatsapp-settings.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.whatsapp-settings.index')}
                                                className={`side-menu__item${route().current('admin.whatsapp-settings.*') ? ' active' : ''}`}
                                            >
                                                <i className="ri-settings-3-line side-menu__icon" />
                                                الإعدادات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.whatsapp-instances.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.whatsapp-instances.index')}
                                                className={`side-menu__item${route().current('admin.whatsapp-instances.*') ? ' active' : ''}`}
                                            >
                                                <i className="ri-smartphone-line side-menu__icon" />
                                                الأرقام
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.whatsapp-send.create') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.whatsapp-send.create')}
                                                className={`side-menu__item${route().current('admin.whatsapp-send.*') ? ' active' : ''}`}
                                            >
                                                <i className="ri-send-plane-2-line side-menu__icon" />
                                                إرسال
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.whatsapp-campaigns.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.whatsapp-campaigns.index')}
                                                className={`side-menu__item${route().current('admin.whatsapp-campaigns.*') ? ' active' : ''}`}
                                            >
                                                <i className="ri-megaphone-line side-menu__icon" />
                                                الحملات
                                            </Link>
                                        </li>
                                    )}
                                    {can('admin.whatsapp-messages.list') && (
                                        <li className="slide">
                                            <Link
                                                href={route('admin.whatsapp-messages.index')}
                                                className={`side-menu__item${route().current('admin.whatsapp-messages.*') ? ' active' : ''}`}
                                            >
                                                <i className="ri-chat-history-line side-menu__icon" />
                                                السجل
                                            </Link>
                                        </li>
                                    )}
                                </ul>
                            </li>
                        )}
                        <li className={`slide${isProfile ? ' active' : ''}`}>
                            <Link
                                href={route('admin.profile.edit')}
                                className={`side-menu__item${isProfile ? ' active' : ''}`}
                            >
                                <i className="ri-user-settings-line side-menu__icon" />
                                <span className="side-menu__label">الملف الشخصي</span>
                            </Link>
                        </li>
                    </ul>
                    <div className="slide-right" id="slide-right">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="#7b8191"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                        >
                            <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z" />
                        </svg>
                    </div>
                </nav>
            </div>
        </aside>
    );
}
