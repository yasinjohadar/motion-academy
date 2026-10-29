import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import DeleteConfirmModal from '@/Components/Admin/DeleteConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const MIGRATION_STATUS = {
    pending: { label: 'في الانتظار', color: '#64748b' },
    running: { label: 'جارٍ النقل', color: '#1a83c1' },
    completed: { label: 'اكتمل', color: '#059669' },
    failed: { label: 'فشل', color: '#dc2626' },
};

function CurrentStorage({ current }) {
    const local = current.is_local;
    const color = local ? '#047857' : '#1a83c1';

    return (
        <div
            className="d-flex align-items-center gap-3 rounded-3 p-3 mb-3"
            style={{ background: local ? '#e7f8f1' : '#e8f3fb', border: `1px solid ${color}33`, color }}
        >
            <i className={local ? 'ri-server-line' : 'ri-cloud-line'} style={{ fontSize: 32 }} />
            <div>
                <div className="small">الملفات مخزنة الآن على</div>
                <div className="fw-bold fs-5">
                    {local ? 'السيرفر المحلي' : `${current.name} (${current.driver_label})`}
                </div>
            </div>
        </div>
    );
}

function MigrationProgress({ migration }) {
    const status = MIGRATION_STATUS[migration.status] || MIGRATION_STATUS.pending;

    return (
        <div className="border rounded-3 p-3 mb-3">
            <div className="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <strong>
                    نقل الملفات: {migration.from} ← {migration.to}
                </strong>
                <span className="badge" style={{ background: status.color }}>
                    {status.label}
                </span>
            </div>
            <div className="progress mb-2" style={{ height: 10 }}>
                <div
                    className={`progress-bar${migration.in_progress ? ' progress-bar-striped progress-bar-animated' : ''}`}
                    style={{ width: `${migration.percent}%`, background: status.color }}
                />
            </div>
            <div className="small text-muted d-flex flex-wrap gap-3">
                <span>
                    {migration.copied + migration.skipped + migration.failed} / {migration.total} ملف
                </span>
                <span>نُسخ: {migration.copied}</span>
                <span>موجود مسبقاً: {migration.skipped}</span>
                {migration.failed > 0 && <span className="text-danger">فشل: {migration.failed}</span>}
            </div>
            {migration.in_progress && (
                <div className="small mt-2">
                    يبقى الموقع على التخزين الحالي حتى ينتهي النقل، ثم يتحوّل تلقائياً. يمكنك مغادرة الصفحة.
                </div>
            )}
            {migration.last_error && !migration.in_progress && migration.status !== 'completed' && (
                <div className="alert alert-danger small mt-2 mb-0">{migration.last_error}</div>
            )}
        </div>
    );
}

function SwitchStorage({ current, configs, migration }) {
    const options = [
        ...(current.is_local ? [] : [{ value: 'local', label: 'السيرفر المحلي' }]),
        ...configs
            .filter((config) => config.is_active && !config.is_current)
            .map((config) => ({ value: String(config.id), label: `${config.name} (${config.driver_label})` })),
    ];
    const [target, setTarget] = useState(options[0]?.value || '');
    const [sending, setSending] = useState(false);
    const busy = migration?.in_progress;

    if (options.length === 0) {
        return (
            <p className="text-muted small mb-0">
                لا يوجد تخزين آخر للتبديل إليه. أضف حساباً سحابياً أولاً، ثم اختبره.
            </p>
        );
    }

    return (
        <div>
            <label className="form-label">نقل كل الملفات والتبديل إلى</label>
            <div className="d-flex flex-wrap gap-2">
                <select
                    className="form-select"
                    style={{ maxWidth: 360 }}
                    value={target}
                    disabled={busy}
                    onChange={(e) => setTarget(e.target.value)}
                >
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <button
                    type="button"
                    className="admin-btn admin-btn-primary"
                    disabled={busy || sending || !target}
                    onClick={() => {
                        if (
                            !window.confirm(
                                'سيتم نسخ كل الصور والفيديوهات إلى التخزين الجديد، ثم يتحوّل الموقع إليه تلقائياً. الملفات القديمة لا تُحذف. متابعة؟',
                            )
                        ) {
                            return;
                        }
                        setSending(true);
                        router.post(
                            route('admin.app-storage.switch'),
                            { target },
                            { preserveScroll: true, onFinish: () => setSending(false) },
                        );
                    }}
                >
                    <i className="ri-arrow-left-right-line" />
                    {sending ? 'جارٍ التحقق...' : 'نقل الملفات والتبديل'}
                </button>
            </div>
            <div className="form-text">
                يُختبر الحساب السحابي أولاً. يحتاج النقل إلى عامل الطوابير (queue worker)، أو نفّذه من السيرفر بالأمر{' '}
                <code dir="ltr">php artisan storage:switch {'{id|local}'} --sync</code>.
            </div>
        </div>
    );
}

export default function Index({ configs, current, migration, flash }) {
    const { can } = useCan();
    const [itemToDelete, setItemToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    // Follow a running migration without a manual refresh — including one started
    // from this page, so polling follows the prop rather than the first render.
    const { start, stop } = usePoll(3000, { only: ['migration', 'current', 'configs'] }, { autoStart: false });
    const inProgress = Boolean(migration?.in_progress);
    useEffect(() => {
        if (inProgress) {
            start();
        } else {
            stop();
        }
        // start/stop are not stable across renders; the status is what matters.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [inProgress]);

    return (
        <AdminUiPage
            title="التخزين"
            subtitle="مكان حفظ الصور والفيديوهات: على السيرفر أو في حساب سحابي"
            flash={flash}
            actions={
                can('admin.app-storage.create') && (
                    <Link href={route('admin.app-storage.create')} className="admin-btn admin-btn-primary">
                        <i className="ri-cloud-line" />
                        حساب سحابي جديد
                    </Link>
                )
            }
        >
            <DeleteConfirmModal
                show={Boolean(itemToDelete)}
                message={itemToDelete ? `هل أنت متأكد من حذف حساب التخزين «${itemToDelete.name}»؟` : ''}
                processing={deleting}
                onConfirm={() => {
                    if (!itemToDelete || deleting) return;
                    setDeleting(true);
                    router.delete(route('admin.app-storage.destroy', itemToDelete.id), {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeleting(false);
                            setItemToDelete(null);
                        },
                    });
                }}
                onCancel={() => !deleting && setItemToDelete(null)}
            />

            <AdminPanel title="مكان تخزين الملفات" icon="ri-hard-drive-2-line" className="mb-4">
                <CurrentStorage current={current} />
                {migration && (migration.in_progress || migration.status === 'failed') && (
                    <MigrationProgress migration={migration} />
                )}
                {migration && migration.status === 'completed' && (
                    <div className="small text-muted mb-3">
                        آخر نقل: {migration.from} ← {migration.to} ({migration.finished_at}) — {migration.copied} ملف.
                    </div>
                )}
                {can('admin.app-storage.switch') && (
                    <SwitchStorage current={current} configs={configs} migration={migration} />
                )}
            </AdminPanel>

            <AdminPanel title="الحسابات السحابية" icon="ri-cloud-line">
                <div className="admin-table-wrap">
                    <div className="table-responsive">
                        <table className="admin-data-table">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                {configs.length === 0 && (
                                    <TableEmptyRow colSpan={4}>
                                        لا توجد حسابات سحابية — الملفات تُخزَّن على السيرفر.
                                    </TableEmptyRow>
                                )}
                                {configs.map((config) => (
                                    <tr key={config.id}>
                                        <td>
                                            <strong>{config.name}</strong>
                                            {config.is_current && (
                                                <span className="admin-badge admin-badge-success ms-2">مستخدم الآن</span>
                                            )}
                                            {config.config_error && (
                                                <div className="text-danger small">تعذّر قراءة المفاتيح — أعد إدخالها</div>
                                            )}
                                            {config.cdn_url && (
                                                <div className="text-muted small" dir="ltr">
                                                    {config.cdn_url}
                                                </div>
                                            )}
                                        </td>
                                        <td>{config.driver_label}</td>
                                        <td>
                                            <span
                                                className={`admin-badge ${config.is_active ? 'admin-badge-success' : 'admin-badge-muted'}`}
                                            >
                                                {config.is_active ? 'نشط' : 'موقوف'}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="d-flex flex-wrap gap-2">
                                                {can('admin.app-storage.test') && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                        onClick={() =>
                                                            router.post(
                                                                route('admin.app-storage.test', config.id),
                                                                {},
                                                                { preserveScroll: true },
                                                            )
                                                        }
                                                    >
                                                        اختبار
                                                    </button>
                                                )}
                                                {config.can?.update && (
                                                    <Link
                                                        href={route('admin.app-storage.edit', config.id)}
                                                        className="admin-btn admin-btn-sm admin-btn-ghost"
                                                    >
                                                        تعديل
                                                    </Link>
                                                )}
                                                {config.can?.delete && !config.is_current && (
                                                    <button
                                                        type="button"
                                                        className="admin-btn admin-btn-sm admin-btn-danger"
                                                        onClick={() => setItemToDelete(config)}
                                                    >
                                                        حذف
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </AdminPanel>
        </AdminUiPage>
    );
}
