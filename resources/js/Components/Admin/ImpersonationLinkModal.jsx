import { createPortal } from 'react-dom';
import { useEffect, useRef, useState } from 'react';

/**
 * Creates and shows the one-time link that opens a student's account in another
 * browser (App\Services\Auth\StudentImpersonation). The link is a credential:
 * it is fetched only when asked for, shown once, and expires on its own.
 */
export default function ImpersonationLinkModal({ user, onClose }) {
    const [state, setState] = useState({ status: 'loading', url: '', expiresAt: null, error: '' });
    const [copied, setCopied] = useState(false);
    const [remaining, setRemaining] = useState('');
    const inputRef = useRef(null);

    useEffect(() => {
        if (!user) {
            return undefined;
        }

        // Mounted fresh per student (key in Users/Index), so state starts clean.
        let cancelled = false;

        window.axios
            .post(route('admin.users.impersonate', user.id))
            .then(({ data }) => {
                if (!cancelled) {
                    setState({
                        status: 'ready',
                        url: data.url,
                        expiresAt: new Date(data.expires_at),
                        error: '',
                    });
                }
            })
            .catch((error) => {
                if (!cancelled) {
                    setState({
                        status: 'error',
                        url: '',
                        expiresAt: null,
                        error: error.response?.data?.message || 'تعذّر إنشاء الرابط. حاول مرة أخرى.',
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [user]);

    // Countdown to expiry.
    useEffect(() => {
        if (!state.expiresAt) {
            return undefined;
        }

        const tick = () => {
            const seconds = Math.max(0, Math.round((state.expiresAt.getTime() - Date.now()) / 1000));
            setRemaining(
                seconds === 0
                    ? 'انتهت صلاحيته'
                    : `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`,
            );
        };
        tick();
        const timer = setInterval(tick, 1000);

        return () => clearInterval(timer);
    }, [state.expiresAt]);

    useEffect(() => {
        if (!user) {
            return undefined;
        }

        const onKeyDown = (event) => event.key === 'Escape' && onClose();
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [user, onClose]);

    if (!user) {
        return null;
    }

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(state.url);
        } catch {
            // Older browsers / non-secure origins: select and copy the old way.
            inputRef.current?.select();
            document.execCommand('copy');
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2500);
    };

    // Rendered into <body>: a fixed overlay inside an animated (transformed)
    // page container would be positioned against that container, not the screen.
    return createPortal(
        <div
            className="admin-delete-modal imp-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="imp-modal-title"
        >
            <button
                type="button"
                className="admin-delete-modal-backdrop"
                aria-label="إغلاق"
                onClick={onClose}
            />

            <div className="admin-delete-modal-dialog">
                <div className="admin-delete-modal-content imp-modal-content">
                    <div className="imp-modal-icon" aria-hidden="true">
                        <i className="ri-login-circle-line" />
                    </div>

                    <h4 id="imp-modal-title" className="admin-delete-modal-title">
                        الدخول إلى حساب {user.name_ar || user.name_en}
                    </h4>
                    <p className="admin-delete-modal-message">
                        انسخ الرابط وافتحه في <strong>متصفح آخر</strong> أو <strong>نافذة خاصة</strong> لتدخل
                        إلى لوحة هذا الطالب وترى ما يراه.
                    </p>

                    {state.status === 'loading' && (
                        <div className="imp-modal-loading">
                            <span className="spinner-border spinner-border-sm" /> جارٍ إنشاء رابط آمن…
                        </div>
                    )}

                    {state.status === 'error' && (
                        <div className="admin-alert admin-alert-error mb-0">{state.error}</div>
                    )}

                    {state.status === 'ready' && (
                        <>
                            <div className="imp-modal-link">
                                <input
                                    ref={inputRef}
                                    type="text"
                                    readOnly
                                    dir="ltr"
                                    value={state.url}
                                    onFocus={(e) => e.target.select()}
                                    aria-label="رابط الدخول"
                                />
                                <button
                                    type="button"
                                    className={`imp-modal-copy${copied ? ' is-copied' : ''}`}
                                    onClick={copy}
                                >
                                    <i className={copied ? 'ri-check-line' : 'ri-file-copy-line'} />
                                    {copied ? 'تم النسخ' : 'نسخ'}
                                </button>
                            </div>

                            <ul className="imp-modal-facts">
                                <li>
                                    <i className="ri-time-line" /> صالح لمدة{' '}
                                    <strong className="en-text">{remaining}</strong>
                                </li>
                                <li>
                                    <i className="ri-fingerprint-line" /> يُستخدم <strong>مرة واحدة</strong>{' '}
                                    فقط
                                </li>
                                <li>
                                    <i className="ri-shield-check-line" /> يُسجَّل في سجل الأمان
                                </li>
                            </ul>

                            <p className="imp-modal-warning">
                                <i className="ri-error-warning-line" /> لا تفتحه في هذا المتصفح: سيخرجك من
                                حسابك كأدمن. ولا تشاركه مع أحد — من يملكه يدخل حساب الطالب.
                            </p>
                        </>
                    )}

                    <div className="admin-delete-modal-actions">
                        <button type="button" className="admin-btn admin-btn-ghost" onClick={onClose}>
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
