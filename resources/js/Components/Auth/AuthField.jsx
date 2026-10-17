import { useId, useState } from 'react';

/**
 * Text field for the auth pages: an icon, a floating focus ring, the server error
 * under it, and — for passwords — a show/hide toggle.
 */
export default function AuthField({
    label,
    icon,
    type = 'text',
    value,
    onChange,
    error,
    hint,
    trailing,
    ...inputProps
}) {
    const id = useId();
    const [revealed, setRevealed] = useState(false);
    const isPassword = type === 'password';

    return (
        <div className={`auth-field${error ? ' has-error' : ''}`}>
            <div className="auth-field-top">
                <label htmlFor={id}>{label}</label>
                {trailing}
            </div>
            <div className="auth-input">
                {icon && <i className={`${icon} auth-input-icon`} aria-hidden="true" />}
                <input
                    id={id}
                    type={isPassword && revealed ? 'text' : type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                    {...inputProps}
                />
                {isPassword && (
                    <button
                        type="button"
                        className="auth-reveal"
                        onClick={() => setRevealed((shown) => !shown)}
                        aria-label={revealed ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'}
                        tabIndex={-1}
                    >
                        <i className={revealed ? 'ri-eye-off-line' : 'ri-eye-line'} />
                    </button>
                )}
            </div>
            {error ? (
                <p className="auth-error" id={`${id}-error`} role="alert">
                    <i className="ri-error-warning-line" /> {error}
                </p>
            ) : (
                hint && <p className="auth-hint">{hint}</p>
            )}
        </div>
    );
}
