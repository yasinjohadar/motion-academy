import ActivityTable, { eventLabel } from '@/Components/Admin/ActivityTable';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Security({ items, filters, options, flash }) {
    const [event, setEvent] = useState(filters.event || '');
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const unchanged = event === (filters.event || '') && search === (filters.search || '');

        if (unchanged) {
            return undefined;
        }

        const timer = setTimeout(() => {
            router.get(
                route('admin.observability.security'),
                {
                    ...(event ? { event } : {}),
                    ...(search ? { search } : {}),
                },
                { preserveState: true, replace: true, preserveScroll: true },
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [event, search, filters.event, filters.search]);

    return (
        <AdminUiPage
            title="السجل الأمني"
            subtitle="الدخول والخروج والمحاولات الفاشلة وتغييرات الصلاحيات"
            chips={[{ label: 'إجمالي الأحداث', value: items.total, icon: 'ri-shield-keyhole-line' }]}
            flash={flash}
            actions={
                <div className="d-flex flex-wrap gap-2">
                    <Link
                        href={route('admin.observability.sessions')}
                        className="admin-btn admin-btn-ghost"
                    >
                        <i className="ri-computer-line" />
                        الجلسات النشطة
                    </Link>
                    <Link
                        href={route('admin.observability.index')}
                        className="admin-btn admin-btn-ghost"
                    >
                        <i className="ri-arrow-right-line" />
                        رجوع للمراقبة
                    </Link>
                </div>
            }
        >
            <AdminPanel title="الأحداث الأمنية" icon="ri-shield-keyhole-line">
                <div className="card-toolbar mb-3">
                    <div className="admin-filters w-100 d-flex flex-wrap gap-2">
                        <div className="search-input-wrap flex-grow-1">
                            <i className="ri-search-line" />
                            <input
                                type="search"
                                className="form-control"
                                placeholder="بحث بالبريد أو الـIP..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
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

                <ActivityTable
                    items={items}
                    showLogName={false}
                    emptyMessage="لا توجد أحداث أمنية مسجّلة."
                />
            </AdminPanel>
        </AdminUiPage>
    );
}
