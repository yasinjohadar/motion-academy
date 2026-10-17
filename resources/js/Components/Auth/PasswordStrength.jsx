/**
 * A four-step meter under a new password. It only guides; the server rules decide.
 */
const LEVELS = [
    { label: 'ضعيفة جداً', tone: 'weak' },
    { label: 'ضعيفة', tone: 'weak' },
    { label: 'مقبولة', tone: 'fair' },
    { label: 'جيدة', tone: 'good' },
    { label: 'قوية', tone: 'strong' },
];

export function passwordScore(password) {
    if (!password) {
        return 0;
    }
    let score = 0;
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    return Math.min(4, score);
}

export default function PasswordStrength({ password }) {
    if (!password) {
        return null;
    }

    const score = passwordScore(password);
    const level = LEVELS[score];

    return (
        <div className={`auth-strength is-${level.tone}`} aria-live="polite">
            <div className="auth-strength-bars">
                {[1, 2, 3, 4].map((step) => (
                    <span key={step} className={step <= score ? 'on' : ''} />
                ))}
            </div>
            <span className="auth-strength-label">قوة كلمة المرور: {level.label}</span>
        </div>
    );
}
