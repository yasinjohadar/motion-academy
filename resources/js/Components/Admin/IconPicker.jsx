export default function IconPicker({ value, options = [], onChange, error = null }) {
    return (
        <div>
            <div className="d-flex align-items-center gap-2 mb-2">
                <span
                    className="d-inline-flex align-items-center justify-content-center rounded"
                    style={{
                        width: 36,
                        height: 36,
                        background: 'rgba(59, 130, 246, 0.12)',
                        color: '#2563eb',
                        fontSize: 18,
                    }}
                >
                    <i className={value || 'ri-link'} />
                </span>
                <select
                    className={`form-select${error ? ' is-invalid' : ''}`}
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                >
                    {options.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            </div>
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </div>
    );
}
