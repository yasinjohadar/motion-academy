import ActivityTable, { eventLabel, logNameLabel } from '@/Components/Admin/ActivityTable';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Activity({ items, filters, options, flash }) {
    const [logName, setLogName] = useState(filters.log_name || '');
    const [event, setEvent] = useState(filters.event || '');
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const unchanged =
            logName === (filters.log_name || '') &&
            event === (filters.event || '') &&
            search === (filters.search || '');

        if (unchanged) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.observability.activity'),
                {
                    ...(logName ? { log_name: logName } : {}),
                    ...(event ? { event } : {}),
                    ...(search ? { search } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [logName, event, search, filters.log_name, filters.event, filters.search]);

    return (
        <AdminUiPage
            title="سجل التدقيق"
            subtitle="من غيّر ماذا ومتى"
            chips={[{ label: 'إجمالي السجلات', value: items.total, icon: 'ri-history-line' }]}
            flash={flash}
            actions={
                <Link
                    href={route('admin.observability.index')}
                    className="admin-btn admin-btn-ghost"
                >
                    <i className="ri-arrow-right-line" />
                    رجوع للمراقبة
                </Link>
            }
        >
            <AdminPanel title="التغييرات المسجّلة" icon="ri-history-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث في الوصف أو الخصائص أو الـIP..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <select
                            className="form-select w-auto"
                            value={logName}
                            onChange={(e) => setLogName(e.target.value)}
                        >
                            <option value="">كل الفئات</option>
                            {(options.log_names || []).map((name) => (
                                <option key={name} value={name}>
                                    {logNameLabel(name)}
                                </option>
                            ))}
                        </select>
                        <select
                            className="form-select w-auto"
                            value={event}
                            onChange={(e) => setEvent(e.target.value)}
                        >
                            <option value="">كل الأحداث</option>
                            {(options.events || []).map((name) => (
                                <option key={name} value={name}>
                                    {eventLabel(name)}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <ActivityTable items={items} emptyMessage="لا توجد تغييرات مسجّلة." />
            </AdminPanel>
        </AdminUiPage>
    );
}
