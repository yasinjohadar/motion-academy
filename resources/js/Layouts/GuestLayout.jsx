import '../../css/auth.css';
import { useEffect } from 'react';

const DEFAULT_ASIDE = {
    heading: 'نحوّل أفكارك إلى حركة وحياة',
    text: 'أكاديمية متخصصة في التحريك الرقمي وصناعة المحتوى البصري.',
};

const POINTS = [
    { icon: 'ri-play-circle-line', text: 'دروس فيديو عملية خطوة بخطوة' },
    { icon: 'ri-lock-unlock-line', text: 'درس مجاني للمعاينة في كل دورة' },
    { icon: 'ri-time-line', text: 'تعلّم بالسرعة التي تناسبك' },
];

/**
 * Auth pages (login, register, password reset, verification).
 * A brand panel beside the form on wide screens; the form alone, with the logo
 * on top, on phones.
 */
export default function GuestLayout({
    title,
    subtitle,
    icon = 'ri-user-line',
    aside = DEFAULT_ASIDE,
    children,
}) {
    useEffect(() => {
        document.documentElement.setAttribute('dir', 'rtl');
        document.documentElement.setAttribute('lang', 'ar');
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    }, []);

    return (
        <div className="auth-shell">
            {/* ---------- Brand panel ---------- */}
            <aside className="auth-aside" aria-hidden="true">
                <div className="auth-aside-waves">
                    <span />
                    <span />
                    <i />
                    <i />
                    <i />
                </div>

                <a href="/" className="auth-aside-logo" tabIndex={-1}>
                    <img src="/frontend/assets/images/logo-light.png" alt="" />
                </a>

                <div className="auth-aside-copy">
                    <h2>{aside.heading}</h2>
                    <p>{aside.text}</p>
                </div>

                {/* A small motion-editor timeline, playing on a loop */}
                <div className="auth-timeline">
                    <div className="auth-timeline-screen">
                        <span className="auth-timeline-shape" />
                        <span className="auth-timeline-play">
                            <i className="ri-play-fill" />
                        </span>
                    </div>
                    <div className="auth-timeline-tracks">
                        <div>
                            <b style={{ '--s': '4%', '--w': '40%' }} />
                        </div>
                        <div>
                            <b style={{ '--s': '30%', '--w': '36%' }} />
                        </div>
                        <div>
                            <b style={{ '--s': '56%', '--w': '38%' }} />
                        </div>
                        <span className="auth-playhead" />
                    </div>
                </div>

                <ul className="auth-points">
                    {POINTS.map((point) => (
                        <li key={point.text}>
                            <i className={point.icon} />
                            {point.text}
                        </li>
                    ))}
                </ul>
            </aside>

            {/* ---------- Form ---------- */}
            <main className="auth-main">
                <div className="auth-topbar">
                    <a href="/" className="auth-back">
                        <i className="ri-arrow-right-line" /> العودة للموقع
                    </a>
                </div>

                <div className="auth-card">
                    <a href="/" className="auth-mobile-logo">
                        <img src="/frontend/assets/images/logo-light.png" alt="Motion Academy" />
                    </a>

                    <div className="auth-head">
                        <span className="auth-head-icon">
                            <i className={icon} />
                        </span>
                        <h1>{title}</h1>
                        {subtitle && <p>{subtitle}</p>}
                    </div>

                    {children}
                </div>

                <p className="auth-foot" dir="ltr">
                    © {new Date().getFullYear()} Motion Academy ·{' '}
                    <a href="/" className="auth-foot-link">
                        We turn ideas into life
                    </a>
                </p>
            </main>
        </div>
    );
}
