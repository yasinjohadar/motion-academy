import AdminLayout from '@/Layouts/Admin/AdminLayout';
import { Head } from '@inertiajs/react';

export default function AdminUiPage({
    title,
    eyebrow = 'لوحة التحكم',
    eyebrowIcon = 'ri-layout-grid-fill',
    subtitle,
    chips = [],
    actions = null,
    flash,
    children,
}) {
    return (
        <AdminLayout>
            <Head title={title} />
            <div className="main-content app-content admin-ui">
                <div className="container-fluid">
                    {flash?.success && <div className="admin-alert admin-alert-success">{flash.success}</div>}
                    {flash?.warning && <div className="admin-alert admin-alert-warning">{flash.warning}</div>}
                    {flash?.error && <div className="admin-alert admin-alert-error">{flash.error}</div>}

                    <header className="admin-hero">
                        <div className="admin-hero__row">
                            <div>
                                <div className="admin-hero__eyebrow">
                                    {eyebrowIcon && <i className={eyebrowIcon} />}
                                    {eyebrow}
                                </div>
                                <h1>{title}</h1>
                                {subtitle && <p>{subtitle}</p>}
                                {chips.length > 0 && (
                                    <div className="admin-chips">
                                        {chips.map((chip) => (
                                            <span key={chip.label} className="admin-chip">
                                                {chip.icon && <i className={chip.icon} />}
                                                <span>{chip.label}</span>
                                                {chip.value != null && <strong>{chip.value}</strong>}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {actions && <div className="d-flex flex-wrap gap-2">{actions}</div>}
                        </div>
                    </header>

                    {children}
                </div>
            </div>
        </AdminLayout>
    );
}

export function AdminPanel({ title, icon = 'ri-settings-4-line', action = null, children, className = '' }) {
    return (
        <section className={`admin-panel ${className}`.trim()}>
            {(title || action) && (
                <div className="admin-panel__head">
                    <h2>
                        {icon && <i className={icon} />}
                        {title}
                    </h2>
                    {action}
                </div>
            )}
            <div className="admin-panel__body">{children}</div>
        </section>
    );
}
