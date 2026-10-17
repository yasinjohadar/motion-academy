import { useEffect, useRef, useState } from 'react';

/** A checkbox that can show the "some selected" state. */
function GroupCheckbox({ checked, indeterminate, onChange, label }) {
    const ref = useRef(null);

    useEffect(() => {
        if (ref.current) {
            ref.current.indeterminate = indeterminate;
        }
    }, [indeterminate]);

    return (
        <input
            ref={ref}
            type="checkbox"
            className="pp-check"
            checked={checked}
            onChange={(e) => onChange(e.target.checked)}
            aria-label={label}
        />
    );
}

/**
 * Permissions grouped as an accordion: every group starts folded, its header shows
 * how many of its permissions are on, and a search opens the groups that match.
 */
export default function PermissionPicker({ groups = [], selected = [], onChange, error }) {
    const selectedSet = new Set(selected);
    const [open, setOpen] = useState({});
    const [query, setQuery] = useState('');

    const term = query.trim().toLowerCase();
    const matches = (permission) =>
        !term ||
        permission.label.toLowerCase().includes(term) ||
        permission.name.toLowerCase().includes(term);

    const visibleGroups = groups
        .map((group) => {
            const groupMatches = term && group.label.toLowerCase().includes(term);

            return {
                ...group,
                visible: groupMatches ? group.permissions : group.permissions.filter(matches),
            };
        })
        .filter((group) => group.visible.length > 0);

    const total = groups.reduce((sum, group) => sum + group.permissions.length, 0);

    const toggle = (name) =>
        onChange(selectedSet.has(name) ? selected.filter((item) => item !== name) : [...selected, name]);

    const toggleGroup = (group, checked) => {
        const names = group.permissions.map((item) => item.name);
        onChange(
            checked
                ? [...new Set([...selected, ...names])]
                : selected.filter((name) => !names.includes(name)),
        );
    };

    const setAllOpen = (value) => setOpen(Object.fromEntries(groups.map((group) => [group.key, value])));
    const allNames = groups.flatMap((group) => group.permissions.map((item) => item.name));

    return (
        <div className="pp">
            <div className="pp-toolbar">
                <div className="pp-search">
                    <i className="ri-search-line" />
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="ابحث في الصلاحيات…"
                    />
                </div>
                <span className="pp-total">
                    <strong className="en-text">{selected.length}</strong> /{' '}
                    <span className="en-text">{total}</span> مفعّلة
                </span>
                <div className="pp-toolbar-actions">
                    <button type="button" className="pp-tool" onClick={() => setAllOpen(true)}>
                        <i className="ri-expand-up-down-line" /> توسيع الكل
                    </button>
                    <button type="button" className="pp-tool" onClick={() => setAllOpen(false)}>
                        <i className="ri-contract-up-down-line" /> طي الكل
                    </button>
                    <button type="button" className="pp-tool" onClick={() => onChange(allNames)}>
                        <i className="ri-checkbox-multiple-line" /> تحديد الكل
                    </button>
                    <button type="button" className="pp-tool" onClick={() => onChange([])}>
                        <i className="ri-checkbox-indeterminate-line" /> إلغاء الكل
                    </button>
                </div>
            </div>

            {visibleGroups.length === 0 && (
                <div className="pp-empty">
                    <i className="ri-search-eye-line" /> لا توجد صلاحيات تطابق «{query}».
                </div>
            )}

            <div className="pp-groups">
                {visibleGroups.map((group) => {
                    const count = group.permissions.filter((item) => selectedSet.has(item.name)).length;
                    const all = count === group.permissions.length;
                    // A search opens every group that has a match.
                    const isOpen = term ? true : Boolean(open[group.key]);
                    const state = all ? 'is-all' : count > 0 ? 'is-some' : 'is-none';

                    return (
                        <section key={group.key} className={`pp-group ${state}${isOpen ? ' is-open' : ''}`}>
                            <div
                                className="pp-head"
                                onClick={(e) => {
                                    if (!e.target.closest('input, button')) {
                                        setOpen((current) => ({
                                            ...current,
                                            [group.key]: !current[group.key],
                                        }));
                                    }
                                }}
                            >
                                <GroupCheckbox
                                    checked={all}
                                    indeterminate={count > 0 && !all}
                                    onChange={(checked) => toggleGroup(group, checked)}
                                    label={`كل صلاحيات ${group.label}`}
                                />
                                <button
                                    type="button"
                                    className="pp-title"
                                    onClick={() =>
                                        setOpen((current) => ({
                                            ...current,
                                            [group.key]: !current[group.key],
                                        }))
                                    }
                                    aria-expanded={isOpen}
                                >
                                    <strong>{group.label}</strong>
                                </button>
                                <span className="pp-count">
                                    <span className="en-text">{count}</span>/
                                    <span className="en-text">{group.permissions.length}</span>
                                </span>
                                <span className="pp-bar" aria-hidden="true">
                                    <span style={{ width: `${(count / group.permissions.length) * 100}%` }} />
                                </span>
                                <span className="pp-chevron" aria-hidden="true">
                                    <i className="ri-arrow-down-s-line" />
                                </span>
                            </div>

                            <div className="pp-body" aria-hidden={!isOpen}>
                                <div className="pp-body-inner">
                                    <div className="pp-items">
                                        {group.visible.map((permission) => (
                                            <label
                                                key={permission.name}
                                                className={`pp-item${selectedSet.has(permission.name) ? ' is-on' : ''}`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="pp-check"
                                                    checked={selectedSet.has(permission.name)}
                                                    onChange={() => toggle(permission.name)}
                                                    tabIndex={isOpen ? 0 : -1}
                                                />
                                                <span>
                                                    <strong>{permission.label}</strong>
                                                    <small dir="ltr">{permission.name}</small>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>
                    );
                })}
            </div>

            {error && <div className="text-danger mt-2">{error}</div>}
        </div>
    );
}
