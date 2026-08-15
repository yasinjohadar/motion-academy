import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useState } from 'react';

const NOTE_TONES = {
    danger: 'admin-badge-danger',
    warning: 'admin-badge-warning',
    info: 'admin-badge-accent',
    success: 'admin-badge-success',
};

const NOTE_ICONS = {
    danger: 'ri-error-warning-line',
    warning: 'ri-alert-line',
    info: 'ri-information-line',
    success: 'ri-checkbox-circle-line',
};

const SETTING_LABELS = {
    driver: 'المحرّك',
    host: 'المضيف',
    port: 'المنفذ',
    database: 'قاعدة البيانات',
    username: 'اسم المستخدم',
    unix_socket: 'المقبس',
    charset: 'الترميز',
    collation: 'الترتيب',
    prefix: 'بادئة الجداول',
    engine: 'محرّك التخزين',
    schema: 'المخطط',
    search_path: 'مسار البحث',
    strict: 'الوضع الصارم',
    foreign_key_constraints: 'قيود المفاتيح الأجنبية',
    journal_mode: 'وضع السجل',
    busy_timeout: 'مهلة الانشغال',
    transaction_mode: 'وضع المعاملات',
    sslmode: 'وضع SSL',
};

function TableDetail({ table }) {
    const [detail, setDetail] = useState(null);
    const [error, setError] = useState(null);

    // Loaded on demand — introspecting every table up front would be wasteful.
    useEffect(() => {
        let cancelled = false;

        setDetail(null);
        setError(null);

        window.axios
            .get(route('admin.observability.database.table', table))
            .then((response) => {
                if (!cancelled) {
                    setDetail(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('تعذّر تحميل تفاصيل الجدول.');
                }
            });

        return () => {
            cancelled = true;
        };
    }, [table]);

    if (error) {
        return <p className="text-danger fs-12 mb-0">{error}</p>;
    }

    if (!detail) {
        return <p className="text-muted fs-12 mb-0">جارٍ التحميل…</p>;
    }

    return (
        <div className="row g-3">
            <div className="col-12 col-lg-6">
                <h6 className="fs-13 mb-2">الأعمدة ({detail.columns.length})</h6>
                <div className="admin-table-wrap">
                    <table className="admin-data-table mb-0">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>النوع</th>
                                <th>يقبل الفراغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detail.columns.map((column) => (
                                <tr key={column.name}>
                                    <td dir="ltr">
                                        <code>{column.name}</code>
                                        {column.auto_increment && (
                                            <span className="admin-badge admin-badge-accent ms-1">AI</span>
                                        )}
                                    </td>
                                    <td dir="ltr" className="fs-12">
                                        {column.type}
                                    </td>
                                    <td>{column.nullable ? 'نعم' : 'لا'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="col-12 col-lg-6">
                <h6 className="fs-13 mb-2">الفهارس ({detail.indexes.length})</h6>
                {detail.indexes.length === 0 ? (
                    <p className="text-muted fs-12">لا توجد فهارس.</p>
                ) : (
                    <div className="admin-table-wrap mb-3">
                        <table className="admin-data-table mb-0">
                            <tbody>
                                {detail.indexes.map((index) => (
                                    <tr key={index.name}>
                                        <td dir="ltr">
                                            <code className="fs-11">{index.name}</code>
                                            <span className="d-block text-muted fs-11">
                                                {index.columns.join(', ')}
                                            </span>
                                        </td>
                                        <td style={{ width: '1%' }}>
                                            {index.primary && (
                                                <span className="admin-badge admin-badge-accent">أساسي</span>
                                            )}
                                            {!index.primary && index.unique && (
                                                <span className="admin-badge admin-badge-success">فريد</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <h6 className="fs-13 mb-2">
                    المفاتيح الأجنبية ({detail.foreign_keys.length})
                </h6>
                {detail.foreign_keys.length === 0 ? (
                    <p className="text-muted fs-12 mb-0">لا توجد مفاتيح أجنبية.</p>
                ) : (
                    <div className="admin-table-wrap">
                        <table className="admin-data-table mb-0">
                            <tbody>
                                {detail.foreign_keys.map((fk) => (
                                    <tr key={fk.name || fk.columns.join('-')}>
                                        <td dir="ltr" className="fs-12">
                                            {fk.columns.join(', ')} →{' '}
                                            <strong>{fk.foreign_table}</strong>.
                                            {fk.foreign_columns.join(', ')}
                                            {fk.on_delete && (
                                                <span className="d-block text-muted fs-11">
                                                    on delete: {fk.on_delete}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Database({ connection, tables = [], totals, notes = [], flash }) {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState({ key: 'rows', dir: 'desc' });
    const [expanded, setExpanded] = useState(null);

    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();

        return tables
            .filter((table) => !term || table.name.toLowerCase().includes(term))
            .slice()
            .sort((a, b) => {
                const av = a[sort.key] ?? 0;
                const bv = b[sort.key] ?? 0;
                const cmp =
                    typeof av === 'string' ? av.localeCompare(bv) : Number(av) - Number(bv);

                return sort.dir === 'asc' ? cmp : -cmp;
            });
    }, [tables, search, sort]);

    const toggleSort = (key) =>
        setSort((current) =>
            current.key === key
                ? { key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
                : { key, dir: 'desc' },
        );

    const sortIcon = (key) =>
        sort.key !== key ? 'ri-arrow-up-down-line' : sort.dir === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line';

    const stats = [
        {
            label: 'المحرّك',
            value: connection.driver_title,
            hint: connection.version,
            icon: 'ri-database-2-line',
            tone: 'primary',
        },
        { label: 'الجداول', value: totals.tables, hint: null, icon: 'ri-table-line', tone: 'info' },
        {
            label: 'إجمالي الصفوف',
            value: Number(totals.rows).toLocaleString('en-US'),
            hint: null,
            icon: 'ri-list-ordered',
            tone: 'success',
        },
        {
            label: 'الحجم الكلي',
            value: totals.size_human ?? '—',
            hint: totals.free_human && totals.free_bytes > 0 ? `حرة: ${totals.free_human}` : null,
            icon: 'ri-hard-drive-2-line',
            tone: 'warning',
        },
    ];

    return (
        <AdminUiPage
            title="قاعدة البيانات"
            subtitle="تفاصيل الاتصال والجداول وأحجامها"
            flash={flash}
            actions={
                <Link href={route('admin.observability.index')} className="admin-btn admin-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    رجوع للمراقبة
                </Link>
            }
        >
            <div className="row g-3 mb-4">
                {stats.map((stat) => (
                    <div className="col-12 col-sm-6 col-lg-3" key={stat.label}>
                        <div className="admin-panel h-100">
                            <div className="admin-panel__body d-flex align-items-center gap-3">
                                <span className={`avatar avatar-md bg-${stat.tone}-transparent flex-shrink-0`}>
                                    <i className={`${stat.icon} fs-20`} />
                                </span>
                                <div className="overflow-hidden">
                                    <div className="fs-20 fw-semibold lh-1 text-truncate" dir="ltr">
                                        {stat.value}
                                    </div>
                                    <div className="text-muted fs-12">{stat.label}</div>
                                    {stat.hint && (
                                        <div className="text-muted fs-11" dir="ltr">
                                            {stat.hint}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="row g-3 mb-4">
                <div className="col-12 col-lg-5">
                    <AdminPanel title="تفاصيل الاتصال" icon="ri-plug-line">
                        <div className="admin-table-wrap">
                            <table className="admin-data-table mb-0">
                                <tbody>
                                    <tr>
                                        <td style={{ width: '45%' }}>اسم الاتصال</td>
                                        <td dir="ltr">
                                            <code>{connection.name}</code>
                                        </td>
                                    </tr>
                                    {Object.entries(connection.settings || {}).map(([key, value]) => (
                                        <tr key={key}>
                                            <td>{SETTING_LABELS[key] || key}</td>
                                            <td dir="ltr" className="text-break">
                                                <code className="fs-11">{value}</code>
                                            </td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <td>اتصالات معرّفة</td>
                                        <td dir="ltr" className="fs-12">
                                            {(connection.available_connections || []).join(', ')}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p className="text-muted fs-11 mt-3 mb-0">
                            كلمة مرور قاعدة البيانات وعنوان الاتصال الكامل لا يُرسلان إلى المتصفح إطلاقاً.
                        </p>
                    </AdminPanel>
                </div>

                <div className="col-12 col-lg-7">
                    <AdminPanel title="ملاحظات وتحذيرات" icon="ri-stethoscope-line">
                        <div className="d-flex flex-column gap-3">
                            {notes.map((note) => (
                                <div key={note.title} className="d-flex gap-2">
                                    <span className={`admin-badge ${NOTE_TONES[note.level]} flex-shrink-0`}>
                                        <i className={NOTE_ICONS[note.level]} />
                                    </span>
                                    <div>
                                        <strong className="d-block fs-13">{note.title}</strong>
                                        <span className="text-muted fs-12">{note.detail}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </AdminPanel>
                </div>
            </div>

            <AdminPanel title={`الجداول (${tables.length})`} icon="ri-table-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100">
                        <div className="search-input-wrap">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث باسم الجدول..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                dir="ltr"
                            />
                        </div>
                    </div>
                </div>

                {totals.size_is_estimate === false && tables.some((t) => t.size_is_estimate) && (
                    <div className="admin-alert admin-alert-info">
                        الحجم الكلي دقيق، لكن أحجام الجداول الفردية تقديرية على هذا المحرّك — راجع
                        الملاحظات أعلاه.
                    </div>
                )}

                <div className="admin-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th role="button" onClick={() => toggleSort('name')}>
                                    الجدول <i className={sortIcon('name')} />
                                </th>
                                <th role="button" onClick={() => toggleSort('rows')}>
                                    الصفوف <i className={sortIcon('rows')} />
                                </th>
                                <th role="button" onClick={() => toggleSort('size_bytes')}>
                                    حجم البيانات <i className={sortIcon('size_bytes')} />
                                </th>
                                <th role="button" onClick={() => toggleSort('indexes')}>
                                    الفهارس <i className={sortIcon('indexes')} />
                                </th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((table) => (
                                <Fragment key={table.name}>
                                    <tr>
                                        <td dir="ltr">
                                            <code>{table.name}</code>
                                            {table.engine && (
                                                <span className="d-block text-muted fs-11">
                                                    {table.engine}
                                                </span>
                                            )}
                                        </td>
                                        <td dir="ltr">
                                            {Number(table.rows).toLocaleString('en-US')}
                                            {!table.rows_exact && (
                                                <span className="text-muted fs-11"> ~</span>
                                            )}
                                        </td>
                                        <td dir="ltr">
                                            {table.size_human ?? <span className="text-muted">—</span>}
                                            {table.size_is_estimate && table.size_human && (
                                                <span
                                                    className="text-muted fs-11"
                                                    title="تقديري — لا يشمل الفهارس ولا مساحة الصفحات"
                                                >
                                                    {' '}
                                                    تقديري
                                                </span>
                                            )}
                                        </td>
                                        <td dir="ltr">{table.indexes}</td>
                                        <td>
                                            <button
                                                type="button"
                                                className="admin-action-btn"
                                                title={expanded === table.name ? 'إخفاء' : 'التفاصيل'}
                                                onClick={() =>
                                                    setExpanded(expanded === table.name ? null : table.name)
                                                }
                                            >
                                                <i className="ri-list-settings-line" />
                                            </button>
                                        </td>
                                    </tr>
                                    {expanded === table.name && (
                                        <tr>
                                            <td colSpan={5}>
                                                <TableDetail table={table.name} />
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-center text-muted py-4">
                                        لا توجد جداول مطابقة.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </AdminPanel>
        </AdminUiPage>
    );
}
