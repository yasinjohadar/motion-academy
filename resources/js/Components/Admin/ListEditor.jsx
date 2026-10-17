/**
 * Editable list of short strings ("what you'll learn", requirements, ...).
 * Enter in the last row adds a new one; empty rows are dropped on the server.
 */
export default function ListEditor({
    label,
    icon = 'ri-check-line',
    items = [],
    onChange,
    placeholder,
    errors = {},
    field,
}) {
    const list = items.length ? items : [''];

    const update = (index, value) => onChange(list.map((item, i) => (i === index ? value : item)));
    const remove = (index) => onChange(list.filter((_, i) => i !== index));
    const add = () => onChange([...list, '']);

    return (
        <div className="mb-3">
            {label && <label className="form-label">{label}</label>}
            <div className="list-editor">
                {list.map((item, index) => {
                    const error = errors[`${field}.${index}`];
                    return (
                        <div key={index} className="list-editor__row">
                            <i className={`${icon} list-editor__icon`} />
                            <input
                                type="text"
                                className={`form-control${error ? ' is-invalid' : ''}`}
                                value={item}
                                placeholder={placeholder}
                                onChange={(e) => update(index, e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        if (index === list.length - 1 && item.trim() !== '') {
                                            add();
                                        }
                                    }
                                }}
                            />
                            <button
                                type="button"
                                className="admin-action-btn admin-action-delete"
                                title="حذف"
                                disabled={list.length === 1 && item === ''}
                                onClick={() => remove(index)}
                            >
                                <i className="ri-close-line" />
                            </button>
                            {error && <div className="invalid-feedback d-block w-100">{error}</div>}
                        </div>
                    );
                })}
            </div>
            <button type="button" className="admin-btn admin-btn-ghost mt-2" onClick={add}>
                <i className="ri-add-line" />
                إضافة عنصر
            </button>
            {errors[field] && <div className="invalid-feedback d-block">{errors[field]}</div>}
        </div>
    );
}
