/**
 * Repeatable rows of objects (videos, features, testimonials...) with move up /
 * down and delete. `renderRow(item, update, index)` draws the inputs of one row;
 * `update(key, value)` changes a field of that row.
 */
export default function RepeaterField({
    items = [],
    onChange,
    renderRow,
    newItem,
    addLabel = 'إضافة',
    max = 20,
    emptyText = 'لا توجد عناصر بعد — لن يظهر هذا القسم في الصفحة.',
}) {
    const update = (index, key, value) =>
        onChange(items.map((item, i) => (i === index ? { ...item, [key]: value } : item)));

    const move = (index, step) => {
        const next = [...items];
        [next[index], next[index + step]] = [next[index + step], next[index]];
        onChange(next);
    };

    return (
        <div>
            {items.length === 0 && <p className="text-muted small mb-3">{emptyText}</p>}
            {items.map((item, index) => (
                <div key={index} className="border rounded-3 p-3 mb-3 position-relative">
                    <div className="d-flex justify-content-between align-items-center mb-2">
                        <span className="badge bg-light text-dark border">#{index + 1}</span>
                        <div className="btn-group btn-group-sm">
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                disabled={index === 0}
                                onClick={() => move(index, -1)}
                                aria-label="تحريك للأعلى"
                            >
                                <i className="ri-arrow-up-line" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                disabled={index === items.length - 1}
                                onClick={() => move(index, 1)}
                                aria-label="تحريك للأسفل"
                            >
                                <i className="ri-arrow-down-line" />
                            </button>
                            <button
                                type="button"
                                className="btn btn-outline-danger"
                                onClick={() => onChange(items.filter((_, i) => i !== index))}
                                aria-label="حذف"
                            >
                                <i className="ri-delete-bin-line" />
                            </button>
                        </div>
                    </div>
                    {renderRow(item, (key, value) => update(index, key, value), index)}
                </div>
            ))}
            {items.length < max && (
                <button type="button" className="admin-btn admin-btn-ghost" onClick={() => onChange([...items, newItem()])}>
                    <i className="ri-add-line" />
                    {addLabel}
                </button>
            )}
        </div>
    );
}
