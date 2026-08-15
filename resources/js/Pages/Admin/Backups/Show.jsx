import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';
import RestoreConfirmModal from '@/Components/Admin/RestoreConfirmModal';
import useCan from '@/hooks/useCan';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function ProgressBlock({ label, percent, stageLabel, animated }) {
    return (
        <div className="mb-3">
            <div className="text-muted small mb-1">{label}</div>
            <div className="progress" style={{ height: 12 }}>
                <div
                    className={`progress-bar${animated ? ' progress-bar-striped progress-bar-animated' : ''}`}
                    role="progressbar"
                    style={{ width: `${percent || 0}%` }}
                />
            </div>
            <div className="small mt-1">
                {percent || 0}% — {stageLabel || '—'}
            </div>
        </div>
    );
}

export default function Show({ backup: initialBackup, queue: pageQueue, flash }) {
    const { can } = useCan();
    const sharedQueue = usePage().props.queue;
    const queue = pageQueue || sharedQueue || {};
    const [backup, setBackup] = useState(initialBackup);
    const [showRestore, setShowRestore] = useState(false);
    const [confirmText, setConfirmText] = useState('');
    const [restoring, setRestoring] = useState(false);
    const [runningSync, setRunningSync] = useState(false);

    const restore = backup.restore;
    const restoreActive = ['pending', 'running'].includes(restore?.status);
    const createActive = ['pending', 'running'].includes(backup.status);
    const waitingForWorker =
        Boolean(queue.needs_worker) &&
        (backup.status === 'pending' || restore?.status === 'pending');

    useEffect(() => {
        setBackup(initialBackup);
    }, [initialBackup]);

    useEffect(() => {
        if (!createActive && !restoreActive) {
            return undefined;
        }

        let cancelled = false;

        const poll = async () => {
            try {
                const response = await fetch(route('admin.backups.status', backup.id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok || cancelled) return;
                const data = await response.json();
                setBackup(data);

                const createDone = ['completed', 'failed'].includes(data.status);
                const restoreStatus = data.restore?.status;
                const restoreDone =
                    !restoreStatus || ['completed', 'failed'].includes(restoreStatus);

                if (
                    createDone &&
                    restoreDone &&
                    !['pending', 'running'].includes(data.status) &&
                    !['pending', 'running'].includes(restoreStatus)
                ) {
                    // keep polling until both settle; then soft reload once
                }
            } catch {
                // ignore
            }
        };

        poll();
        const timer = setInterval(poll, 1000);

        return () => {
            cancelled = true;
            clearInterval(timer);
        };
    }, [backup.id, createActive, restoreActive]);

    const logs = useMemo(() => backup.logs || [], [backup.logs]);

    return (
        <AdminUiPage
            title={backup.name}
            subtitle={`${backup.backup_type_label} · ${backup.storage_config?.name || '—'}`}
            flash={flash}
            actions={
                <div className="d-flex flex-wrap gap-2">
                    <Link href={route('admin.backups.index')} className="admin-btn admin-btn-ghost">
                        العودة
                    </Link>
                    {can('admin.backups.download') && backup.status === 'completed' && (
                        <a
                            href={route('admin.backups.download', backup.id)}
                            className="admin-btn admin-btn-primary"
                        >
                            تنزيل
                        </a>
                    )}
                    {can('admin.backups.restore') &&
                        backup.status === 'completed' &&
                        !restoreActive && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-danger"
                                onClick={() => setShowRestore(true)}
                            >
                                استرجاع
                            </button>
                        )}
                    {can('admin.backups.create') &&
                        ['failed', 'pending'].includes(backup.status) && (
                            <button
                                type="button"
                                className="admin-btn admin-btn-ghost"
                                onClick={() =>
                                    router.post(route('admin.backups.run', backup.id))
                                }
                            >
                                إعادة التشغيل
                            </button>
                        )}
                </div>
            }
        >
            <RestoreConfirmModal
                show={showRestore}
                backupName={backup.name}
                processing={restoring}
                confirmText={confirmText}
                onConfirmTextChange={setConfirmText}
                onCancel={() => {
                    if (!restoring) {
                        setShowRestore(false);
                        setConfirmText('');
                    }
                }}
                onConfirm={() => {
                    setRestoring(true);
                    router.post(
                        route('admin.backups.restore', backup.id),
                        { confirm: 1 },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setRestoring(false);
                                setShowRestore(false);
                                setConfirmText('');
                            },
                        },
                    );
                }}
            />

            {waitingForWorker && (
                <div className="alert alert-warning mb-3" role="alert">
                    <strong>الطابور متوقف — لن يبدأ التقدم حتى تشغّل العامل.</strong>
                    <div className="mt-2 small">
                        اتصال الطابور الحالي:{' '}
                        <code>{queue.connection || 'database'}</code>
                    </div>
                    <div className="mt-2">
                        نفّذ في الطرفية:
                        <pre className="mb-0 mt-1 p-2 bg-light border rounded">
                            {queue.worker_command || 'php artisan queue:work'}
                        </pre>
                    </div>
                    {can('admin.backups.create') && backup.status === 'pending' && (
                        <button
                            type="button"
                            className="admin-btn admin-btn-primary mt-2"
                            disabled={runningSync}
                            onClick={() => {
                                setRunningSync(true);
                                router.post(
                                    route('admin.backups.run-sync', backup.id),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setRunningSync(false),
                                    },
                                );
                            }}
                        >
                            {runningSync
                                ? 'جاري التشغيل المباشر...'
                                : 'تشغيل الآن مباشرة (بدون طابور)'}
                        </button>
                    )}
                </div>
            )}

            <div className="row g-3">
                <div className="col-lg-5">
                    <AdminPanel title="حالة النسخ" icon="ri-database-2-line">
                        <div className="mb-3">
                            <div className="text-muted small">حالة النسخ</div>
                            <strong>{backup.status_label || backup.status}</strong>
                        </div>

                        <ProgressBlock
                            label="تقدم النسخ"
                            percent={backup.progress}
                            stageLabel={backup.stage_label || backup.stage}
                            animated={createActive}
                        />

                        {restore && (
                            <>
                                <hr />
                                <div className="mb-3">
                                    <div className="text-muted small">
                                        حالة الاسترجاع
                                    </div>
                                    <strong>
                                        {restore.status_label || restore.status}
                                    </strong>
                                </div>
                                <ProgressBlock
                                    label="تقدم الاسترجاع"
                                    percent={restore.progress}
                                    stageLabel={
                                        restore.stage_label || restore.stage
                                    }
                                    animated={restoreActive}
                                />
                                {restore.error && (
                                    <div className="alert alert-danger mt-2 mb-0">
                                        {restore.error}
                                    </div>
                                )}
                            </>
                        )}

                        <div className="mb-2 small">
                            الحجم: {backup.file_size_human || '—'}
                        </div>
                        <div className="mb-2 small">
                            المسار: <code>{backup.storage_path || '—'}</code>
                        </div>
                        {backup.error_message && (
                            <div className="alert alert-danger mt-3 mb-0">
                                {backup.error_message}
                            </div>
                        )}
                    </AdminPanel>
                </div>
                <div className="col-lg-7">
                    <AdminPanel title="سجل العملية" icon="ri-file-list-3-line">
                        {createActive || restoreActive ? (
                            <p className="text-muted small mb-3">
                                تحديث مباشر كل ثانية
                            </p>
                        ) : null}
                        <div
                            className="border rounded p-3"
                            style={{ maxHeight: 360, overflowY: 'auto' }}
                        >
                            {logs.length === 0 && (
                                <div className="text-muted">
                                    {waitingForWorker
                                        ? 'بانتظار عامل الطابور لبدء السجلات...'
                                        : 'لا توجد سجلات بعد'}
                                </div>
                            )}
                            {logs.map((log) => (
                                <div key={log.id} className="small mb-2">
                                    <span className="text-muted">{log.created_at}</span>
                                    {' · '}
                                    <span
                                        className={
                                            log.level === 'error' ? 'text-danger' : ''
                                        }
                                    >
                                        {log.message}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </AdminPanel>
                </div>
            </div>
        </AdminUiPage>
    );
}
