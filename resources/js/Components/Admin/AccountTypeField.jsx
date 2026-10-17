/**
 * Every account is an admin (control panel) or a student (public site).
 * Shown as two cards on the user create/edit forms.
 */
const DESCRIPTIONS = {
    admin: { icon: 'ri-shield-user-line', text: 'يدخل لوحة التحكم ويدير الموقع والدورات والطلاب.' },
    student: { icon: 'ri-graduation-cap-line', text: 'يتابع دوراته ودروسه من لوحة الطالب في الموقع.' },
};

export default function AccountTypeField({ value, onChange, types = {}, error }) {
    return (
        <div>
            <label className="form-label">نوع الحساب</label>
            <div className="account-type-options" role="radiogroup" aria-label="نوع الحساب">
                {Object.entries(types).map(([key, label]) => (
                    <label key={key} className={`account-type-option${value === key ? ' is-selected' : ''}`}>
                        <input
                            type="radio"
                            name="account_type"
                            value={key}
                            checked={value === key}
                            onChange={() => onChange(key)}
                        />
                        <span className="account-type-icon">
                            <i className={DESCRIPTIONS[key]?.icon ?? 'ri-user-line'} />
                        </span>
                        <span>
                            <strong className="d-block">{label}</strong>
                            <small className="text-muted">{DESCRIPTIONS[key]?.text}</small>
                        </span>
                    </label>
                ))}
            </div>
            {error && <div className="text-danger mt-1 small">{error}</div>}
        </div>
    );
}
