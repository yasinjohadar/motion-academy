import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * The right-hand off-canvas panel opened from the header.
 *
 * It replaces a 400-line vendored Blade partial whose entire contents were invented
 * English demo data (people who do not exist, events that never happened). That partial
 * sat outside `@inertia`, so it could not read a single real value no matter how it was
 * styled — moving the panel into the React tree is what makes real data possible here.
 *
 * Freshness is free: this reads the same shared `notifications` prop that
 * {@see NotificationBell} keeps current over Echo (or polling when the socket is down),
 * so there is no second subscription and no second request.
 *
 * The id must stay `header-sidebar` — the header trigger opens it through Bootstrap's
 * delegated `data-bs-toggle="offcanvas"` handler.
 */

const TABS = [
    { id: 'notifications', label: 'الإشعارات', icon: 'ri-notification-3-line' },
    { id: 'chat', label: 'المحادثات', icon: 'ri-chat-3-line' },
    { id: 'friends', label: 'الفريق', icon: 'ri-team-line' },
];

export default function HeaderSidebar() {
    const { notifications } = usePage().props;
    const [tab, setTab] = useState('notifications');

    const items = notifications?.items ?? [];
    const unreadCount = notifications?.unread_count ?? 0;

    const markAllRead = () => {
        if (unreadCount === 0) {
            return;
        }

        router.post(
            route('admin.notifications.read-all'),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const openNotification = (event, item) => {
        event.preventDefault();

        // Mark first, then navigate — otherwise the page change cancels the request
        // and the badge keeps counting an item the user has clearly seen.
        if (!item.is_read) {
            router.post(
                route('admin.notifications.read', item.id),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => item.action_url && router.visit(item.action_url),
                },
            );

            return;
        }

        if (item.action_url) {
            router.visit(item.action_url);
        }
    };

    return (
        <div
            /* `admin-ui` is not cosmetic here: the --admin-* design tokens are scoped to
               that class (and .wa-lux), not to :root. Without it every var() below
               resolves to nothing and the panel renders fully transparent. It is also
               what makes [data-theme-mode='dark'] swap the palette. */
            className="offcanvas offcanvas-end hs-panel admin-ui"
            tabIndex="-1"
            id="header-sidebar"
            aria-labelledby="headerSidebarLabel"
        >
            <div className="hs-panel__head">
                <div>
                    <h5 className="hs-panel__title" id="headerSidebarLabel">
                        مركز التنبيهات
                    </h5>
                    <p className="hs-panel__subtitle">
                        {unreadCount > 0
                            ? `${unreadCount} غير مقروء`
                            : 'كل شيء مقروء'}
                    </p>
                </div>
                <button
                    type="button"
                    className="hs-panel__close"
                    data-bs-dismiss="offcanvas"
                    aria-label="إغلاق"
                >
                    <i className="ri-close-line" />
                </button>
            </div>

            <ul className="nav wa-tabs hs-panel__tabs" role="tablist">
                {TABS.map((item) => (
                    <li className="nav-item" key={item.id}>
                        <button
                            type="button"
                            className={`nav-link${tab === item.id ? ' active' : ''}`}
                            onClick={() => setTab(item.id)}
                            aria-current={tab === item.id ? 'page' : undefined}
                        >
                            <i className={item.icon} />
                            {item.label}
                            {item.id === 'notifications' && unreadCount > 0 && (
                                <span className="hs-tab-count">{unreadCount > 99 ? '99+' : unreadCount}</span>
                            )}
                        </button>
                    </li>
                ))}
            </ul>

            <div className="hs-panel__body">
                {tab === 'notifications' && (
                    items.length === 0 ? (
                        <EmptyState
                            icon="ri-notification-off-line"
                            title="لا توجد إشعارات"
                            hint="سيظهر هنا كل ما يستجدّ في النظام — النسخ الاحتياطي، واتساب، المستخدمون."
                        />
                    ) : (
                        <ul className="hs-list">
                            {items.map((item) => (
                                <li key={item.id}>
                                    <a
                                        href={item.action_url || '#'}
                                        onClick={(event) => openNotification(event, item)}
                                        className={`hs-item${item.is_read ? '' : ' is-unread'} hs-item--${item.level}`}
                                    >
                                        <span className="hs-item__icon">
                                            <i className={item.icon} />
                                        </span>
                                        <span className="hs-item__body">
                                            <span className="hs-item__title">{item.title}</span>
                                            <span className="hs-item__message">{item.message}</span>
                                            <span className="hs-item__meta">
                                                {item.category_label && (
                                                    <span className={`admin-badge ${item.category_badge}`}>
                                                        {item.category_label}
                                                    </span>
                                                )}
                                                <span className="hs-item__time">
                                                    <i className="ri-time-line" />
                                                    {item.created_at_human}
                                                </span>
                                            </span>
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )
                )}

                {tab === 'chat' && (
                    <EmptyState
                        icon="ri-chat-3-line"
                        title="لا توجد محادثات هنا بعد"
                        hint="محادثات واتساب تُدار من شاشتها المخصّصة."
                        action={{ href: route('admin.whatsapp-messages.index'), label: 'فتح محادثات واتساب' }}
                    />
                )}

                {tab === 'friends' && (
                    <EmptyState
                        icon="ri-team-line"
                        title="لا يوجد أعضاء متصلون"
                        hint="سيعرض هذا التبويب زملاء الفريق المتصلين حين تُفعَّل الميزة."
                    />
                )}
            </div>

            {tab === 'notifications' && (
                <div className="hs-panel__foot">
                    <button
                        type="button"
                        className="hs-link"
                        onClick={markAllRead}
                        disabled={unreadCount === 0}
                    >
                        <i className="ri-check-double-line" />
                        تعليم الكل كمقروء
                    </button>
                    <div className="hs-panel__foot-links">
                        <Link href={route('admin.notifications.preferences')} className="hs-link">
                            <i className="ri-equalizer-line" />
                            التفضيلات
                        </Link>
                        <Link href={route('admin.notifications.index')} className="hs-link hs-link--accent">
                            عرض الكل
                            <i className="ri-arrow-left-line" />
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Says plainly that there is nothing here, instead of filling the space with invented
 * records — which is exactly what the panel used to do.
 */
function EmptyState({ icon, title, hint, action = null }) {
    return (
        <div className="hs-empty">
            <span className="hs-empty__icon">
                <i className={icon} />
            </span>
            <p className="hs-empty__title">{title}</p>
            <p className="hs-empty__hint">{hint}</p>
            {action && (
                <Link href={action.href} className="hs-link hs-link--accent">
                    {action.label}
                    <i className="ri-arrow-left-line" />
                </Link>
            )}
        </div>
    );
}
