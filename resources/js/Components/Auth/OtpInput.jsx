import { useEffect, useRef } from 'react';

/**
 * One box per digit. Typing moves forward, Backspace moves back, pasting or the
 * phone's SMS/WhatsApp autofill fills every box at once; `onComplete` fires when
 * the last box is filled.
 */
export default function OtpInput({ length = 6, value, onChange, onComplete, error, disabled = false }) {
    const refs = useRef([]);
    const digits = Array.from({ length }, (_, i) => value[i] || '');

    useEffect(() => {
        refs.current[0]?.focus();
    }, []);

    const setAt = (index, char) => {
        const next = digits.slice();
        next[index] = char;
        const joined = next.join('').slice(0, length);
        onChange(joined);
        if (joined.length === length && !joined.includes(' ') && next.every(Boolean)) {
            onComplete?.(joined);
        }
    };

    const fill = (text, from = 0) => {
        const clean = text.replace(/\D+/g, '').slice(0, length - from);
        if (!clean) return;
        const next = digits.slice();
        clean.split('').forEach((char, i) => {
            next[from + i] = char;
        });
        const joined = next.join('');
        onChange(joined);
        refs.current[Math.min(from + clean.length, length - 1)]?.focus();
        if (next.every(Boolean)) onComplete?.(joined);
    };

    return (
        <div className={`otp-boxes${error ? ' has-error' : ''}`} dir="ltr">
            {digits.map((digit, index) => (
                <input
                    key={index}
                    ref={(el) => {
                        refs.current[index] = el;
                    }}
                    className={`otp-box${digit ? ' is-filled' : ''}`}
                    type="text"
                    inputMode="numeric"
                    autoComplete={index === 0 ? 'one-time-code' : 'off'}
                    maxLength={index === 0 ? length : 1}
                    value={digit}
                    disabled={disabled}
                    aria-label={`الرقم ${index + 1}`}
                    onChange={(e) => {
                        const typed = e.target.value.replace(/\D+/g, '');
                        if (typed.length > 1) {
                            fill(typed, index);
                            return;
                        }
                        setAt(index, typed);
                        if (typed && index < length - 1) refs.current[index + 1]?.focus();
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Backspace' && !digit && index > 0) {
                            refs.current[index - 1]?.focus();
                            setAt(index - 1, '');
                        }
                        if (e.key === 'ArrowLeft' && index > 0) refs.current[index - 1]?.focus();
                        if (e.key === 'ArrowRight' && index < length - 1) refs.current[index + 1]?.focus();
                    }}
                    onPaste={(e) => {
                        e.preventDefault();
                        fill(e.clipboardData.getData('text'), 0);
                    }}
                    onFocus={(e) => e.target.select()}
                />
            ))}
        </div>
    );
}
