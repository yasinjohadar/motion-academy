/**
 * Web Push subscription helpers.
 *
 * The permission prompt is only ever triggered from an explicit user action on the
 * preferences screen — never on page load, which browsers penalise and users resent.
 * Requires a secure context (HTTPS, or localhost during development).
 */

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

export function pushSupported() {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        window.isSecureContext
    );
}

export function pushPermission() {
    if (typeof Notification === 'undefined') {
        return 'unsupported';
    }

    return Notification.permission;
}

async function registration() {
    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

export async function currentSubscription() {
    if (!pushSupported()) {
        return null;
    }

    const registered = await registration();

    return registered.pushManager.getSubscription();
}

export async function subscribeToPush() {
    if (!pushSupported()) {
        throw new Error('المتصفح لا يدعم إشعارات الويب، أو الاتصال غير آمن (HTTPS مطلوب).');
    }

    const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;

    if (!vapidKey) {
        throw new Error('مفاتيح VAPID غير مضبوطة على السيرفر.');
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('تم رفض إذن الإشعارات. يمكنك تعديله من إعدادات المتصفح.');
    }

    const registered = await registration();

    const subscription =
        (await registered.pushManager.getSubscription()) ||
        (await registered.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        }));

    const payload = subscription.toJSON();

    const response = await window.axios.post(route('admin.notifications.push.subscribe'), {
        endpoint: payload.endpoint,
        keys: payload.keys,
        content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
    });

    // A session timeout answers with an HTML redirect rather than JSON (this app scopes
    // JSON exception rendering to `api/*`), so confirm we actually got the JSON back.
    if (response.data?.status !== 'subscribed') {
        throw new Error('تعذّر تسجيل الاشتراك — قد تكون الجلسة منتهية. أعد تحميل الصفحة.');
    }

    return response.data;
}

export async function unsubscribeFromPush() {
    const subscription = await currentSubscription();

    if (!subscription) {
        return null;
    }

    const { endpoint } = subscription.toJSON();

    await subscription.unsubscribe();

    return window.axios.delete(route('admin.notifications.push.unsubscribe'), {
        data: { endpoint },
    });
}
