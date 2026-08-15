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
        router.post(
            route('admin.notifications.read-all'),
            {},
            { preserveScroll: true, preserveState: true },
        );
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
            <div className="main-header-dropdown dropdown-menu dropdown-menu-end main-header-message">
                <div className="menu-header-content bg-primary text-fixed-white">
                    <div className="d-flex align-items-center justify-content-between">
                        <h6 className="mb-0 fs-15 fw-semibold text-fixed-white">الإشعارات</h6>
                        {unreadCount > 0 && (
                            <span
                                role="button"
                                onClick={markAllRead}
                                className="badge rounded-pill bg-warning pt-1 text-fixed-black"
                            >
                                تحديد الكل كمقروء
                            </span>
                        )}
                    </div>
                    <p className="dropdown-title-text subtext mb-0 text-fixed-white op-6 pb-0 fs-12">
                        لديك {unreadCount} إشعارات جديدة
                    </p>
                </div>
                <div>
                    <hr className="dropdown-divider" />
                </div>
                <ul className="list-unstyled mb-0" id="header-notification-scroll">
                    {items.length === 0 && (
                        <li className="dropdown-item text-center py-3">
                            <p className="text-muted mb-0">لا توجد إشعارات جديدة</p>
                        </li>
                    )}
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className={`dropdown-item${item.is_read ? '' : ' bg-light-subtle'}`}
                        >
                            <a
                                href="javascript:void(0);"
                                className="d-flex align-items-start gap-2 text-decoration-none"
                                onClick={(e) => openNotification(e, item)}
                            >
                                <span className="avatar avatar-sm bg-primary-transparent flex-shrink-0">
                                    <i className={item.icon} />
                                </span>
                                <span className="flex-grow-1 overflow-hidden">
                                    <span className="d-block fw-semibold fs-13 text-truncate">
                                        {item.title}
                                    </span>
                                    <span className="d-block text-muted fs-12 text-truncate">
                                        {item.message}
                                    </span>
                                    <span className="d-block text-muted op-7 fs-11">
                                        {item.created_at_human}
                                    </span>
                                </span>
                                {!item.is_read && (
                                    <span className="badge bg-success-transparent flex-shrink-0">
                                        جديد
                                    </span>
                                )}
                            </a>
                        </li>
                    ))}
                </ul>
                <div className="text-center dropdown-footer">
                    <Link href={route('admin.notifications.index')} className="text-primary fs-13">
                        عرض الكل
                    </Link>
                </div>
            </div>
        </div>
    );
}
