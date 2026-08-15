import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import {
    currentSubscription,
    pushPermission,
    pushSupported,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/webPush';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * (category × channel) matrix of delivery preferences.
 *
 * The `database` channel renders locked-on: it is the notification centre's source of
 * truth. Channels that cannot deliver right now (no SMTP configured, no phone number,
 * real-time disabled) render disabled with the reason, so the user cannot switch on a
 * channel that would silently drop.
 */
export default function Preferences({ matrix = [], channels = [], availability = {}, flash }) {
    const initial = {};
    matrix.forEach((row) => {
        initial[row.category] = {};
        row.channels.forEach((cell) => {
            initial[row.category][cell.channel] = cell.enabled;
        });
    });

    const { data, setData, put, processing } = useForm({ preferences: initial });

    const [pushState, setPushState] = useState({
        supported: false,
        subscribed: false,
        permission: 'default',
        busy: false,
        error: null,
    });

    useEffect(() => {
        const supported = pushSupported();

        if (!supported) {
            setPushState((s) => ({ ...s, supported: false, permission: pushPermission() }));
            return;
        }

        currentSubscription()
            .then((subscription) =>
                setPushState((s) => ({
                    ...s,
                    supported: true,
                    subscribed: Boolean(subscription),
                    permission: pushPermission(),
                })),
            )
            .catch(() => setPushState((s) => ({ ...s, supported: true })));
    }, []);

    const togglePush = async () => {
        setPushState((s) => ({ ...s, busy: true, error: null }));

        try {
            if (pushState.subscribed) {
                await unsubscribeFromPush();
                setPushState((s) => ({ ...s, subscribed: false, busy: false }));
            } else {
                await subscribeToPush();
                setPushState((s) => ({
                    ...s,
                    subscribed: true,
                    busy: false,
                    permission: pushPermission(),
                }));
            }

            // Availability of the webpush channel depends on having a subscription.
            router.reload({ only: ['availability'] });
        } catch (e) {
            setPushState((s) => ({
                ...s,
                busy: false,
                error: e?.message || 'تعذّر تحديث تسجيل إشعارات المتصفح.',
            }));
        }
    };

    const toggle = (category, channel, value) => {
        setData('preferences', {
            ...data.preferences,
            [category]: {
                ...data.preferences[category],
                [channel]: value,
            },
        });
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.notifications.preferences.update'), { preserveScroll: true });
    };

    return (
        <AdminUiPage
            title="تفضيلات الإشعارات"
            subtitle="اختر كيف تريد استلام كل نوع من الإشعارات"
            flash={flash}
            actions={
                <Link
                    href={route('admin.notifications.index')}
                    className="admin-btn admin-btn-ghost"
                >
                    <i className="ri-arrow-right-line" />
                    رجوع للإشعارات
                </Link>
            }
        >
            <AdminPanel title="إشعارات المتصفح (Web Push)" icon="ri-computer-line">
                {!pushState.supported && (
                    <p className="text-muted mb-0">
                        هذا المتصفح لا يدعم إشعارات الويب، أو الاتصال غير آمن — إشعارات
                        المتصفح تحتاج HTTPS (أو localhost أثناء التطوير).
                    </p>
                )}

                {pushState.supported && (
                    <>
                        <p className="text-muted fs-13">
                            {pushState.subscribed
                                ? 'هذا المتصفح مُسجَّل لاستقبال الإشعارات حتى لو كان التبويب مغلقاً.'
                                : 'سجّل هذا المتصفح لاستقبال الإشعارات حتى لو كان التبويب مغلقاً. سيُطلب إذن الإشعارات عند الضغط.'}
                        </p>

                        {pushState.permission === 'denied' && (
                            <div className="admin-alert admin-alert-warning">
                                إذن الإشعارات محظور في هذا المتصفح — يجب السماح به من إعدادات
                                الموقع في المتصفح أولاً.
                            </div>
                        )}

                        {pushState.error && (
                            <div className="admin-alert admin-alert-error">{pushState.error}</div>
                        )}

                        <button
                            type="button"
                            className={`admin-btn ${pushState.subscribed ? 'admin-btn-ghost' : 'admin-btn-primary'}`}
                            onClick={togglePush}
                            disabled={pushState.busy}
                        >
                            <i
                                className={
                                    pushState.subscribed
                                        ? 'ri-notification-off-line'
                                        : 'ri-notification-3-line'
                                }
                            />
                            {pushState.subscribed
                                ? 'إلغاء تسجيل هذا المتصفح'
                                : 'تسجيل هذا المتصفح'}
                        </button>
                    </>
                )}
            </AdminPanel>

            <form onSubmit={submit}>
                <AdminPanel title="القنوات لكل فئة" icon="ri-equalizer-line">
                    <div className="admin-table-wrap">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الفئة</th>
                                    {channels.map((channel) => (
                                        <th key={channel.value} className="text-center">
                                            <i className={`${channel.icon} me-1`} />
                                            {channel.label}
                                            {availability[channel.value]?.available === false && (
                                                <span
                                                    className="d-block text-muted fs-11 fw-normal"
                                                    title={availability[channel.value]?.reason}
                                                >
                                                    غير متاح
                                                </span>
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {matrix.map((row) => (
                                    <tr key={row.category}>
                                        <td>
                                            <i className={`${row.icon} me-1`} />
                                            <strong>{row.label}</strong>
                                        </td>
                                        {row.channels.map((cell) => {
                                            const info = availability[cell.channel] || {};
                                            const unavailable = info.available === false;
                                            const disabled =
                                                cell.locked || unavailable || processing;

                                            return (
                                                <td key={cell.channel} className="text-center">
                                                    <input
                                                        type="checkbox"
                                                        className="form-check-input"
                                                        checked={
                                                            cell.locked
                                                                ? true
                                                                : Boolean(
                                                                      data.preferences[
                                                                          row.category
                                                                      ]?.[cell.channel],
                                                                  )
                                                        }
                                                        disabled={disabled}
                                                        title={
                                                            cell.locked
                                                                ? 'الإشعارات داخل التطبيق مُفعّلة دائماً'
                                                                : info.reason || undefined
                                                        }
                                                        onChange={(e) =>
                                                            toggle(
                                                                row.category,
                                                                cell.channel,
                                                                e.target.checked,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <p className="text-muted fs-12 mt-3 mb-0">
                        الإشعارات داخل التطبيق مُفعّلة دائماً ولا يمكن إلغاؤها — هي المصدر
                        الأساسي لمركز الإشعارات. القنوات المعطّلة تحتاج إعداداً على مستوى
                        النظام أو ملفك الشخصي.
                    </p>

                    <div className="d-flex gap-2 mt-4">
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={processing}
                        >
                            <i className="ri-save-line" />
                            حفظ التفضيلات
                        </button>
                    </div>
                </AdminPanel>
            </form>
        </AdminUiPage>
    );
}
