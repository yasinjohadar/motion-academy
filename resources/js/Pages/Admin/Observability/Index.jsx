import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import useCan from '@/hooks/useCan';
import { Link } from '@inertiajs/react';

/**
 * Observability hub — the counters that say whether anything needs attention,
 * plus the entry points to each screen.
 */
export default function Index({ stats, queue, flash }) {
    const { can } = useCan();

    const tiles = [
        {
            label: 'تغييرات مسجّلة (24 ساعة)',
            value: stats.audit_today,
            icon: 'ri-history-line',
            tone: 'info',
            href: can('admin.observability.activity') ? route('admin.observability.activity') : null,
        },
        {
            label: 'محاولات دخول فاشلة (24 ساعة)',
            value: stats.failed_logins_today,
            icon: 'ri-shield-keyhole-line',
            tone: stats.failed_logins_today > 0 ? 'warning' : 'success',
            href: can('admin.observability.security') ? route('admin.observability.security') : null,
        },
        {
            label: 'مهام فاشلة',
            value: stats.failed_jobs,
            icon: 'ri-error-warning-line',
            tone: stats.failed_jobs > 0 ? 'danger' : 'success',
            href: can('admin.observability.jobs') ? route('admin.observability.failed-jobs') : null,
        },
        {
            label: 'جلسات نشطة',
            value: stats.active_sessions,
            icon: 'ri-computer-line',
            tone: 'info',
            href: can('admin.observability.security') ? route('admin.observability.sessions') : null,
        },
        {
            label: 'أحداث webhook غير معالجة',
            value: stats.unprocessed_webhooks,
            icon: 'ri-webhook-line',
            tone: stats.unprocessed_webhooks > 0 ? 'warning' : 'success',
            href: can('admin.observability.jobs') ? route('admin.observability.webhooks') : null,
        },
        {
            label: 'ملفات سجلات',
            value: stats.log_files,
            icon: 'ri-file-list-3-line',
            tone: 'info',
            href: can('admin.observability.logs') ? '/admin/logs' : null,
        },
    ];

    return (
        <AdminUiPage
            title="المراقبة والسجلات"
            subtitle="كل ما يحدث في النظام في مكان واحد"
            flash={flash}
        >
            {queue?.needs_worker && queue.pending > 0 && (
                <div className="admin-alert admin-alert-warning">
                    <strong>يوجد {queue.pending} مهمة في الانتظار.</strong> تأكّد من تشغيل عامل
                    القوائم بالطوابير الصحيحة:
                    <code className="d-block mt-2" dir="ltr">
                        {queue.worker_command}
                    </code>
                </div>
            )}

            <div className="row g-3 mb-4">
                {tiles.map((tile) => {
                    const body = (
                        <div className="admin-panel h-100">
                            <div className="admin-panel__body d-flex align-items-center gap-3">
                                <span className={`avatar avatar-md bg-${tile.tone}-transparent flex-shrink-0`}>
                                    <i className={`${tile.icon} fs-20`} />
                                </span>
                                <div>
                                    <div className="fs-24 fw-semibold lh-1" dir="ltr">
                                        {tile.value ?? 0}
                                    </div>
                                    <div className="text-muted fs-12">{tile.label}</div>
                                </div>
                            </div>
                        </div>
                    );

                    return (
                        <div className="col-12 col-sm-6 col-lg-4" key={tile.label}>
                            {tile.href ? (
                                <Link href={tile.href} className="text-decoration-none d-block h-100">
                                    {body}
                                </Link>
                            ) : (
                                body
                            )}
                        </div>
                    );
                })}
            </div>

            <AdminPanel title="الشاشات" icon="ri-links-line">
                <div className="d-flex flex-wrap gap-2">
                    {can('admin.observability.activity') && (
                        <Link
                            href={route('admin.observability.activity')}
                            className="admin-btn admin-btn-primary"
                        >
                            <i className="ri-history-line" />
                            سجل التدقيق
                        </Link>
                    )}
                    {can('admin.observability.security') && (
                        <>
                            <Link
                                href={route('admin.observability.security')}
                                className="admin-btn admin-btn-ghost"
                            >
                                <i className="ri-shield-keyhole-line" />
                                السجل الأمني
                            </Link>
                            <Link
                                href={route('admin.observability.sessions')}
                                className="admin-btn admin-btn-ghost"
                            >
                                <i className="ri-computer-line" />
                                الجلسات النشطة
                            </Link>
                        </>
                    )}
                    {can('admin.observability.jobs') && (
                        <>
                            <Link
                                href={route('admin.observability.failed-jobs')}
                                className="admin-btn admin-btn-ghost"
                            >
                                <i className="ri-error-warning-line" />
                                المهام الفاشلة
                            </Link>
                            <Link
                                href={route('admin.observability.webhooks')}
                                className="admin-btn admin-btn-ghost"
                            >
                                <i className="ri-webhook-line" />
                                أحداث Webhook
                            </Link>
                        </>
                    )}
                    {can('admin.observability.database') && (
                        <Link
                            href={route('admin.observability.database')}
                            className="admin-btn admin-btn-ghost"
                        >
                            <i className="ri-database-2-line" />
                            قاعدة البيانات
                        </Link>
                    )}
                    {can('admin.observability.logs') && (
                        <a href="/admin/logs" className="admin-btn admin-btn-ghost">
                            <i className="ri-terminal-box-line" />
                            ملفات السجلات
                            <span className="badge bg-secondary-transparent ms-1">تقني</span>
                        </a>
                    )}
                </div>
                <p className="text-muted fs-12 mt-3 mb-0">
                    شاشة «ملفات السجلات» أداة تقنية بواجهة إنجليزية منفصلة، تعرض محتوى
                    <code dir="ltr"> storage/logs </code> مباشرة.
                </p>
            </AdminPanel>
        </AdminUiPage>
    );
}
