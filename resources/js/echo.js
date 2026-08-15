import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Lazily creates the Echo instance.
 *
 * Deliberately NOT initialised at boot: whether real-time is on is decided by the
 * server (config/notifications.php → the `notifications.realtime.enabled` shared prop),
 * and creating Echo eagerly would open a socket to a Reverb that may not be running,
 * filling the console with reconnect errors on every page load.
 *
 * Callers ask for it only after the server has said real-time is available.
 */
let echo = null;

export function ensureEcho() {
    if (echo) {
        return echo;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echo;

    return echo;
}

export function echoInstance() {
    return echo;
}
