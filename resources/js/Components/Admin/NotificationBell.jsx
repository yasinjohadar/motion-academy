import { ensureEcho } from '@/echo';
import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Header notification bell.
 *
 * Reads the `notifications` prop shared by HandleInertiaRequests, so it is correct on
 * first paint with no extra request. Staying fresh afterwards has two modes:
 *
 *   - Real-time: window.Echo is connected and pushes new notifications instantly.
 *   - Polling:   a partial Inertia reload of the `notifications` prop only.
 *
 * Polling runs whenever real-time is unavailable OR the socket drops, and stops again
 * once the socket reconnects. The bell therefore never depends on Reverb being up.
 */
export default function NotificationBell() {
    const { auth, notifications } = usePage().props;
    const userId = auth?.user?.id;

    const realtimeEnabled = Boolean(notifications?.realtime?.enabled);
    const pollInterval = notifications?.realtime?.poll_interval || 30000;

    const [live, setLive] = useState([]);
    const [socketConnected, setSocketConnected] = useState(false);
    const pollTimer = useRef(null);

    const serverItems = notifications?.items ?? [];
    const serverUnread = notifications?.unread_count ?? 0;

    // Notifications pushed over the socket since the last server response, minus any
    // the server has caught up on — prevents a moment of double-listing after reload.
    const pending = live.filter((item) => !serverItems.some((existing) => existing.id === item.id));
    const items = [...pending, ...serverItems].slice(0, 10);
    const unreadCount = serverUnread + pending.filter((item) => !item.is_read).length;

    const refresh = useCallback(() => {
        router.reload({ only: ['notifications'] });
    }, []);

    // Drop socket-delivered items once the server response includes them.
    useEffect(() => {
        setLive((current) =>
            current.filter((item) => !serverItems.some((existing) => existing.id === item.id)),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverUnread, serverItems.length]);

    // Real-time subscription. Echo is created only once the server confirms real-time
    // is available, so a stopped Reverb never produces console noise.
    useEffect(() => {
        if (!realtimeEnabled || !userId) {
            return undefined;
        }

        const instance = ensureEcho();

        if (!instance) {
            return undefined;
        }

        const channel = instance.private(`App.Models.User.${userId}`);

        channel.notification((payload) => {
            setLive((current) => [
                {
                    id: payload.id,
                    title: payload.title ?? '',
                    message: payload.message ?? '',
                    action_url: payload.action_url ?? null,
                    icon: payload.icon ?? 'ri-notification-3-line',
                    category: payload.category ?? null,
                    category_label: payload.category_label ?? null,
                    level: payload.level ?? 'info',
                    level_badge: payload.level_badge ?? 'admin-badge-accent',
                    is_read: false,
                    created_at_human: 'الآن',
                },
                ...current,
            ]);
        });

        // Drives the polling fallback below: if the socket is not carrying the load,
        // partial reloads take over until it reconnects.
        const connection = instance.connector?.pusher?.connection;
        const onConnected = () => setSocketConnected(true);
        const onDisconnected = () => setSocketConnected(false);

        if (connection) {
            setSocketConnected(connection.state === 'connected');
            connection.bind('connected', onConnected);
            connection.bind('disconnected', onDisconnected);
            connection.bind('unavailable', onDisconnected);
            connection.bind('failed', onDisconnected);
        }

        return () => {
            if (connection) {
                connection.unbind('connected', onConnected);
                connection.unbind('disconnected', onDisconnected);
                connection.unbind('unavailable', onDisconnected);
                connection.unbind('failed', onDisconnected);
            }

            instance.leave(`App.Models.User.${userId}`);
        };
    }, [realtimeEnabled, userId]);

    // Polling fallback — active whenever the socket is not carrying the load.
    useEffect(() => {
        const needsPolling = !userId ? false : !realtimeEnabled || !socketConnected;

        if (!needsPolling) {
            if (pollTimer.current) {
                clearInterval(pollTimer.current);
                pollTimer.current = null;
            }
            return undefined;
        }

        pollTimer.current = setInterval(refresh, pollInterval);

        return () => {
            if (pollTimer.current) {
                clearInterval(pollTimer.current);
                pollTimer.current = null;
            }
        };
    }, [userId, realtimeEnabled, socketConnected, pollInterval, refresh]);

    const markAllRead = (e) => {
        e.preventDefault();

        if (unreadCount === 0) {
            return;
        }

        setLive([]);
        router.post(route('admin.notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
    };

    const openNotification = (e, item) => {
        e.preventDefault();

        if (!item.is_read) {
            router.post(
                route('admin.notifications.read', item.id),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => {
                        if (item.action_url) {
                            router.visit(item.action_url);
                        }
                    },
                },
            );
            return;
        }

        if (item.action_url) {
            router.visit(item.action_url);
        }
    };

    const isLive = realtimeEnabled && socketConnected;

    return (
        <div className="header-element notifications-dropdown main-header-notification">
            <a
                href="javascript:void(0);"
                className="header-link dropdown-toggle"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                id="messageDropdown"
                aria-expanded="false"
                title="الإشعارات"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="header-link-icon"
                    height="24px"
                    viewBox="0 0 24 24"
                    width="24px"
                    fill="currentColor"
                >
                    <path d="M0 0h24v24H0V0z" fill="none" />
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z" />
                </svg>
                {unreadCount > 0 && (
                    <>
                        <span className="pulse-success" />
                        <span className="badge bg-danger rounded-pill header-icon-badge">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    </>
                )}
            </a>

            <div className="dropdown-menu dropdown-menu-end nb-menu">
                <div className="nb-head">
                    <div className="nb-head-top">
                        <div>
                            <h6 className="nb-title">الإشعارات</h6>
                            <p className="nb-subtitle">
                                {unreadCount > 0 ? (
                                    <>
                                        لديك <strong>{unreadCount}</strong>{' '}
                                        {unreadCount === 1 ? 'إشعار جديد' : 'إشعارات جديدة'}
                                    </>
                                ) : (
                                    'كل شيء مقروء — أنت على اطلاع'
                                )}
                            </p>
                        </div>
                        <span
                            className={`nb-live${isLive ? ' is-live' : ''}`}
                            title={isLive ? 'تصل الإشعارات لحظياً' : 'تُحدَّث القائمة دورياً'}
                        >
                            <span className="nb-live-dot" />
                            {isLive ? 'مباشر' : 'تحديث دوري'}
                        </span>
                    </div>
                    {unreadCount > 0 && (
                        <button type="button" className="nb-read-all" onClick={markAllRead}>
                            <i className="ri-check-double-line" /> تحديد الكل كمقروء
                        </button>
                    )}
                </div>

                <ul className="nb-list" id="header-notification-scroll">
                    {items.length === 0 && (
                        <li className="nb-empty">
                            <span className="nb-empty-icon">
                                <i className="ri-notification-off-line" />
                            </span>
                            <strong>لا توجد إشعارات</strong>
                            <span>سنبلغك هنا بكل جديد في الموقع.</span>
                        </li>
                    )}
                    {items.map((item) => (
                        <li key={item.id}>
                            <a
                                href="javascript:void(0);"
                                className={`nb-item is-${item.level || 'info'}${item.is_read ? '' : ' is-unread'}`}
                                onClick={(e) => openNotification(e, item)}
                            >
                                <span className="nb-item-icon">
                                    <i className={item.icon || 'ri-notification-3-line'} />
                                </span>
                                <span className="nb-item-body">
                                    <span className="nb-item-title">{item.title}</span>
                                    {item.message && <span className="nb-item-text">{item.message}</span>}
                                    <span className="nb-item-meta">
                                        {item.category_label && (
                                            <span className="nb-chip">{item.category_label}</span>
                                        )}
                                        <span>
                                            <i className="ri-time-line" /> {item.created_at_human}
                                        </span>
                                    </span>
                                </span>
                                {!item.is_read && <span className="nb-item-dot" aria-label="غير مقروء" />}
                            </a>
                        </li>
                    ))}
                </ul>

                <div className="nb-foot">
                    <Link href={route('admin.notifications.index')} className="nb-foot-main">
                        عرض كل الإشعارات <i className="ri-arrow-left-line" />
                    </Link>
                    <Link
                        href={route('admin.notifications.preferences')}
                        className="nb-foot-icon"
                        title="تفضيلات الإشعارات"
                        aria-label="تفضيلات الإشعارات"
                    >
                        <i className="ri-settings-3-line" />
                    </Link>
                </div>
            </div>
        </div>
    );
}
