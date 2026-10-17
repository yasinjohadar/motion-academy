import { useEffect, useId, useMemo, useRef, useState } from 'react';

/**
 * Country code picker (with search) + local number, for the auth pages.
 * The number field accepts digits only and drops a leading trunk zero, so
 * "0991 234 567" with +963 is sent as country_code=963, phone=991234567.
 */
export default function PhoneField({
    label = 'رقم الهاتف (واتساب)',
    countries = [],
    countryCode,
    phone,
    onCountryChange,
    onPhoneChange,
    error,
    hint,
    autoFocus = false,
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const wrapRef = useRef(null);
    const searchRef = useRef(null);

    const current = countries.find((c) => c.code === String(countryCode)) || countries[0];

    const filtered = useMemo(() => {
        const q = query.trim().replace(/^\+/, '');
        if (!q) return countries;
        return countries.filter(
            (c) =>
                c.name.includes(q) || c.code.startsWith(q) || c.iso.toLowerCase().includes(q.toLowerCase()),
        );
    }, [countries, query]);

    useEffect(() => {
        if (!open) return undefined;
        const close = (event) => {
            if (!wrapRef.current?.contains(event.target)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        setTimeout(() => searchRef.current?.focus(), 30);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const pick = (country) => {
        onCountryChange(country.code);
        setOpen(false);
        setQuery('');
    };

    const onNumber = (value) => {
        let digits = value.replace(/\D+/g, '');
        // The country code pasted into the number field — keep only the local part.
        if (current && digits.startsWith(current.code) && digits.length > current.code.length + 6) {
            digits = digits.slice(current.code.length);
        }
        onPhoneChange(digits.replace(/^0+/, ''));
    };

    return (
        <div className={`auth-field${error ? ' has-error' : ''}`} ref={wrapRef}>
            <div className="auth-field-top">
                <label htmlFor={id}>{label}</label>
            </div>
            <div className="auth-phone">
                <div className="auth-input auth-phone-number">
                    <i className="ri-whatsapp-line auth-input-icon" aria-hidden="true" />
                    <input
                        id={id}
                        type="tel"
                        inputMode="numeric"
                        autoComplete="tel-national"
                        dir="ltr"
                        placeholder="991234567"
                        value={phone}
                        onChange={(e) => onNumber(e.target.value)}
                        aria-invalid={Boolean(error)}
                        autoFocus={autoFocus}
                    />
                </div>
                <button
                    type="button"
                    className="auth-input auth-phone-country"
                    onClick={() => setOpen((o) => !o)}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    aria-label="مفتاح الدولة"
                >
                    <span className="auth-phone-flag">{current?.flag}</span>
                    <span dir="ltr">+{current?.code}</span>
                    <i className="ri-arrow-down-s-line" />
                </button>

                {open && (
                    <div className="auth-country-pop" role="listbox">
                        <div className="auth-country-search">
                            <i className="ri-search-line" />
                            <input
                                ref={searchRef}
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="ابحث باسم الدولة أو المفتاح..."
                                onKeyDown={(e) => {
                                    if (e.key === 'Escape') setOpen(false);
                                    if (e.key === 'Enter' && filtered[0]) {
                                        e.preventDefault();
                                        pick(filtered[0]);
                                    }
                                }}
                            />
                        </div>
                        <ul>
                            {filtered.map((country) => (
                                <li key={country.code + country.iso}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={country.code === current?.code}
                                        className={country.code === current?.code ? 'is-active' : ''}
                                        onClick={() => pick(country)}
                                    >
                                        <span className="auth-phone-flag">{country.flag}</span>
                                        <span className="flex-grow-1 text-start">{country.name}</span>
                                        <span className="auth-country-code" dir="ltr">
                                            +{country.code}
                                        </span>
                                    </button>
                                </li>
                            ))}
                            {filtered.length === 0 && <li className="auth-country-empty">لا توجد نتائج</li>}
                        </ul>
                    </div>
                )}
            </div>
            {error ? (
                <p className="auth-error" role="alert">
                    <i className="ri-error-warning-line" /> {error}
                </p>
            ) : (
                hint && <p className="auth-hint">{hint}</p>
            )}
        </div>
    );
}
