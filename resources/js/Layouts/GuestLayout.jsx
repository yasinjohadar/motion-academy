import { useEffect } from 'react';

export default function GuestLayout({ title, subtitle, children }) {
    useEffect(() => {
        document.documentElement.setAttribute('dir', 'rtl');
        document.documentElement.setAttribute('lang', 'ar');
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }, []);

    return (
        <div className="guest-shell">
            <div className="guest-shell-glow" aria-hidden="true" />
            <div className="guest-card">
                <div className="guest-card-body">
                    <div className="guest-brand-block">
                        <img
                            src="/assets/images/brand-logos/desktop-logo.png"
                            alt="logo"
                            className="guest-logo"
                        />
                        <h1 className="guest-title">{title || 'لوحة التحكم'}</h1>
                        {subtitle && (
                            <p className="guest-subtitle">{subtitle}</p>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
