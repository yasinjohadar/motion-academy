export default function PermissionPicker({
    groups = [],
    selected = [],
    onChange,
    error,
}) {
    const selectedSet = new Set(selected);

    const toggle = (name) => {
        const next = selectedSet.has(name)
            ? selected.filter((item) => item !== name)
            : [...selected, name];
        onChange(next);
    };

    const toggleGroup = (group, checked) => {
        const names = group.permissions.map((item) => item.name);
        if (checked) {
            onChange([...new Set([...selected, ...names])]);
            return;
        }
        onChange(selected.filter((name) => !names.includes(name)));
    };

    return (
        <div className="permission-picker">
            {groups.map((group) => {
                const allChecked = group.permissions.every((item) =>
                    selectedSet.has(item.name),
                );

                return (
                    <div key={group.key} className="permission-group">
                        <div className="permission-group-header">
                            <label className="permission-group-title">
                                <input
                                    type="checkbox"
                                    checked={allChecked}
                                    onChange={(e) => toggleGroup(group, e.target.checked)}
                                />
                                <span>{group.label}</span>
                            </label>
                        </div>
                        <div className="permission-group-body">
                            {group.permissions.map((permission) => (
                                <label key={permission.name} className="permission-item">
                                    <input
                                        type="checkbox"
                                        checked={selectedSet.has(permission.name)}
                                        onChange={() => toggle(permission.name)}
                                    />
                                    <span>
                                        <strong>{permission.label}</strong>
                                        <small>{permission.name}</small>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                );
            })}
            {error && <div className="text-danger mt-2">{error}</div>}
        </div>
    );
}
