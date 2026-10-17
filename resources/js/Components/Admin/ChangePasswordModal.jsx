import { router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Set a student's password from the user details page: generate one (length,
 * character sets, suggestions), edit or copy it, and optionally send the sign-in
 * details over WhatsApp with a templated message.
 *
 * Generation uses crypto.getRandomValues — never Math.random — and happens
 * entirely in the browser; the server only receives the final password.
 */
const SETS = {
    lower: 'abcdefghijkmnopqrstuvwxyz',
    upper: 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    digits: '23456789',
    symbols: '!@#$%&*?-_+=',
};
const AMBIGUOUS_EXTRA = { lower: 'l', upper: 'IO', digits: '01' };
const WORDS = [
    'Motion',
    'Frame',
    'Pixel',
    'Story',
    'Scene',
    'Light',
    'Color',
    'Studio',
    'Vector',
    'Render',
    'Shape',
    'Camera',
    'Spark',
    'Orbit',
    'Wave',
    'Echo',
    'Sketch',
    'Canvas',
];

function randomInt(max) {
    const buffer = new Uint32Array(1);
    // Rejection sampling keeps every value equally likely.
    const limit = Math.floor(0x100000000 / max) * max;
    do {
        crypto.getRandomValues(buffer);
    } while (buffer[0] >= limit);

    return buffer[0] % max;
}

function generate(length, options) {
    const pools = Object.keys(SETS)
        .filter((key) => options[key])
        .map((key) => SETS[key] + (options.ambiguous && AMBIGUOUS_EXTRA[key] ? AMBIGUOUS_EXTRA[key] : ''));

    if (pools.length === 0) {
        return '';
    }

    // One character from every chosen set, then fill, then shuffle.
    const chars = pools.map((pool) => pool[randomInt(pool.length)]);
    const all = pools.join('');
    while (chars.length < length) {
        chars.push(all[randomInt(all.length)]);
    }
    for (let i = chars.length - 1; i > 0; i -= 1) {
        const j = randomInt(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    return chars.join('');
}

function memorable() {
    const first = WORDS[randomInt(WORDS.length)];
    let second = WORDS[randomInt(WORDS.length)];
    while (second === first) {
        second = WORDS[randomInt(WORDS.length)];
    }
    const number = String(1000 + randomInt(9000));

    return `${first}-${second}-${number}${SETS.symbols[randomInt(SETS.symbols.length)]}`;
}

function strength(password) {
    if (!password) {
        return { score: 0, label: '—', tone: 'none' };
    }
    let pool = 0;
    if (/[a-z]/.test(password)) pool += 26;
    if (/[A-Z]/.test(password)) pool += 26;
    if (/\d/.test(password)) pool += 10;
    if (/[^a-zA-Z\d]/.test(password)) pool += 20;
    const bits = password.length * Math.log2(Math.max(pool, 2));

    if (password.length < 8 || bits < 40) return { score: 1, label: 'ضعيفة', tone: 'weak' };
    if (bits < 60) return { score: 2, label: 'متوسطة', tone: 'fair' };
    if (bits < 80) return { score: 3, label: 'قوية', tone: 'good' };

    return { score: 4, label: 'قوية جداً', tone: 'great' };
}

const DEFAULT_OPTIONS = { lower: true, upper: true, digits: true, symbols: false, ambiguous: false };

function suggest(length, options) {
    return [generate(length, options), generate(length, options), memorable()];
}

export default function ChangePasswordModal({ user, credentials, onClose }) {
    const [length, setLength] = useState(12);
    const [options, setOptions] = useState(DEFAULT_OPTIONS);
    // Generated once on open; afterwards only when the admin changes a setting.
    const [suggestions, setSuggestions] = useState(() => suggest(12, DEFAULT_OPTIONS));
    const [password, setPassword] = useState(() => suggestions[0]);
    const [visible, setVisible] = useState(true);
    const [copied, setCopied] = useState(false);
    const [send, setSend] = useState(Boolean(credentials?.whatsapp?.available));
    const [message, setMessage] = useState(credentials?.template ?? '');
    const [saveTemplate, setSaveTemplate] = useState(false);
    const [errors, setErrors] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const messageRef = useRef(null);

    /** New suggestions for these settings; the first one becomes the password. */
    const regenerate = (nextLength, nextOptions) => {
        const list = suggest(nextLength, nextOptions);
        setSuggestions(list);
        setPassword(list[0]);
    };

    const changeLength = (value) => {
        setLength(value);
        regenerate(value, options);
    };

    useEffect(() => {
        const onKeyDown = (event) => event.key === 'Escape' && !submitting && onClose();
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.classList.remove('overflow-hidden');
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [onClose, submitting]);

    const meter = strength(password);
    const whatsapp = credentials?.whatsapp ?? { available: false, reason: '' };

    const preview = useMemo(
        () =>
            (message || '')
                .replaceAll('{name}', user.name_ar || user.name || '')
                .replaceAll('{email}', user.email || '')
                .replaceAll('{phone}', user.phone || '')
                .replaceAll('{password}', visible ? password : '••••••••')
                .replaceAll('{login_url}', credentials?.login_url || '')
                .replaceAll('{site}', credentials?.site || ''),
        [message, user, password, visible, credentials],
    );

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(password);
        } catch {
            // Non-secure origin: nothing more we can do silently.
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const insert = (token) => {
        const field = messageRef.current;
        if (!field) {
            setMessage((current) => current + token);
            return;
        }
        const start = field.selectionStart ?? message.length;
        const end = field.selectionEnd ?? message.length;
        const next = message.slice(0, start) + token + message.slice(end);
        setMessage(next);
        requestAnimationFrame(() => {
            field.focus();
            field.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const toggleOption = (key) => {
        const next = { ...options, [key]: !options[key] };
        // Keep at least one character set on.
        if (!['lower', 'upper', 'digits', 'symbols'].some((set) => next[set])) {
            return;
        }
        setOptions(next);
        regenerate(length, next);
    };

    const submit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});
        router.put(
            route('admin.users.password', user.id),
            {
                password,
                send_whatsapp: send,
                message: send ? message : null,
                save_template: send && saveTemplate,
            },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return createPortal(
        <div
            className="admin-delete-modal pw-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pw-modal-title"
        >
            <button
                type="button"
                className="admin-delete-modal-backdrop"
                aria-label="إغلاق"
                onClick={() => !submitting && onClose()}
            />

            <div className="admin-delete-modal-dialog">
                <form className="admin-delete-modal-content pw-modal-content" onSubmit={submit}>
                    <div className="enr-modal-head">
                        <span className="enr-modal-icon pw-icon" aria-hidden="true">
                            <i className="ri-key-2-line" />
                        </span>
                        <div>
                            <h4 id="pw-modal-title" className="enr-modal-title">
                                تغيير كلمة المرور
                            </h4>
                            <p className="enr-modal-sub">
                                {user.name_ar || user.name} <span dir="ltr">· {user.email}</span>
                            </p>
                        </div>
                    </div>

                    <div className="pw-scroll">
                        {/* ---------- The password ---------- */}
                        <div className="pw-field">
                            <input
                                type={visible ? 'text' : 'password'}
                                dir="ltr"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                aria-label="كلمة المرور"
                                autoComplete="new-password"
                                spellCheck={false}
                            />
                            <button
                                type="button"
                                className="pw-icon-btn"
                                onClick={() => setVisible((v) => !v)}
                                title={visible ? 'إخفاء' : 'إظهار'}
                            >
                                <i className={visible ? 'ri-eye-off-line' : 'ri-eye-line'} />
                            </button>
                            <button
                                type="button"
                                className="pw-icon-btn"
                                onClick={() => setPassword(generate(length, options))}
                                title="توليد كلمة جديدة"
                            >
                                <i className="ri-refresh-line" />
                            </button>
                            <button
                                type="button"
                                className={`pw-copy${copied ? ' is-copied' : ''}`}
                                onClick={copy}
                            >
                                <i className={copied ? 'ri-check-line' : 'ri-file-copy-line'} />{' '}
                                {copied ? 'تم النسخ' : 'نسخ'}
                            </button>
                        </div>
                        {errors.password && <div className="pw-error">{errors.password}</div>}

                        <div className={`pw-meter is-${meter.tone}`}>
                            <span className="pw-meter-bars">
                                {[1, 2, 3, 4].map((n) => (
                                    <i key={n} className={n <= meter.score ? 'is-on' : ''} />
                                ))}
                            </span>
                            <span>
                                القوة: <strong>{meter.label}</strong> ·{' '}
                                <span className="en-text">{password.length}</span> حرفاً
                            </span>
                        </div>

                        {/* ---------- Generator options ---------- */}
                        <div className="pw-options">
                            <label className="pw-length">
                                <span>
                                    الطول <strong className="en-text">{length}</strong>
                                </span>
                                <input
                                    type="range"
                                    min={8}
                                    max={32}
                                    value={length}
                                    onChange={(e) => changeLength(Number(e.target.value))}
                                />
                            </label>
                            <div className="pw-sets">
                                {[
                                    ['lower', 'a-z'],
                                    ['upper', 'A-Z'],
                                    ['digits', '0-9'],
                                    ['symbols', '!@#'],
                                ].map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        className={`pw-set${options[key] ? ' is-on' : ''}`}
                                        onClick={() => toggleOption(key)}
                                        aria-pressed={options[key]}
                                    >
                                        <span className="en-text">{label}</span>
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    className={`pw-set${options.ambiguous ? ' is-on' : ''}`}
                                    onClick={() => toggleOption('ambiguous')}
                                    aria-pressed={options.ambiguous}
                                    title="يسمح بأحرف متشابهة مثل 0 و O و l و 1"
                                >
                                    المتشابهة
                                </button>
                            </div>
                        </div>

                        <div className="pw-suggestions">
                            <span className="pw-label">
                                اقتراحات
                                <button
                                    type="button"
                                    className="pw-link"
                                    onClick={() => setSuggestions(suggest(length, options))}
                                >
                                    <i className="ri-refresh-line" /> غيرها
                                </button>
                            </span>
                            <div className="pw-suggestion-list">
                                {suggestions.map((value, index) => (
                                    <button
                                        key={value}
                                        type="button"
                                        className={`pw-suggestion${value === password ? ' is-active' : ''}`}
                                        onClick={() => setPassword(value)}
                                    >
                                        {index === 2 && <small>سهلة التذكر</small>}
                                        <span dir="ltr">{value}</span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* ---------- WhatsApp ---------- */}
                        <div className={`pw-whatsapp${send ? ' is-on' : ''}`}>
                            <label className="pw-switch">
                                <span className="form-check form-switch m-0">
                                    <input
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={send}
                                        disabled={!whatsapp.available}
                                        onChange={(e) => setSend(e.target.checked)}
                                    />
                                </span>
                                <span>
                                    <strong className="d-block">
                                        <i className="ri-whatsapp-line" /> إرسال بيانات الدخول عبر واتساب
                                    </strong>
                                    <small className="text-muted">
                                        {whatsapp.available
                                            ? `إلى ${user.phone || 'رقم الطالب'} — تُخفى كلمة المرور من سجل الرسائل.`
                                            : whatsapp.reason || 'واتساب غير متاح لهذا الطالب.'}
                                    </small>
                                </span>
                            </label>

                            {send && (
                                <>
                                    <div className="pw-presets">
                                        {Object.entries(credentials.presets).map(([key, preset]) => (
                                            <button
                                                key={key}
                                                type="button"
                                                className={`pw-preset${message === preset.text ? ' is-on' : ''}`}
                                                onClick={() => setMessage(preset.text)}
                                            >
                                                {preset.label}
                                            </button>
                                        ))}
                                    </div>
                                    <div className="pw-tokens">
                                        {Object.entries(credentials.placeholders).map(([token, label]) => (
                                            <button
                                                key={token}
                                                type="button"
                                                className="otp-chip"
                                                onClick={() => insert(token)}
                                                title={label}
                                            >
                                                <code dir="ltr">{token}</code> {label}
                                            </button>
                                        ))}
                                    </div>
                                    <textarea
                                        ref={messageRef}
                                        className={`form-control pw-message${errors.message ? ' is-invalid' : ''}`}
                                        rows={6}
                                        value={message}
                                        onChange={(e) => setMessage(e.target.value)}
                                    />
                                    {errors.message && <div className="pw-error">{errors.message}</div>}

                                    <div className="otp-wa-preview">
                                        <span className="otp-wa-label">
                                            <i className="ri-whatsapp-line" /> معاينة
                                        </span>
                                        <div className="otp-wa-bubble">{preview}</div>
                                    </div>

                                    <label className="pw-save">
                                        <input
                                            type="checkbox"
                                            checked={saveTemplate}
                                            onChange={(e) => setSaveTemplate(e.target.checked)}
                                        />
                                        حفظ هذا النص كقالب افتراضي للمرات القادمة
                                    </label>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="admin-delete-modal-actions">
                        <button
                            type="button"
                            className="admin-btn admin-btn-ghost"
                            disabled={submitting}
                            onClick={onClose}
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            className="admin-btn admin-btn-primary"
                            disabled={submitting || password.length < 8}
                        >
                            {submitting ? (
                                <span className="spinner-border spinner-border-sm" />
                            ) : (
                                <i className={send ? 'ri-send-plane-line' : 'ri-save-line'} />
                            )}
                            {send ? 'حفظ وإرسال عبر واتساب' : 'حفظ كلمة المرور'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}
