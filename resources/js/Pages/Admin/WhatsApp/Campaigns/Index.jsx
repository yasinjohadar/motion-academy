import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import useCan from '@/hooks/useCan';
import { Link } from '@inertiajs/react';

function statusBadge(status) {
    if (status === 'completed' || status === 'sent') return 'wa-badge-ok';
    if (status === 'failed') return 'wa-badge-muted';
    if (status === 'processing' || status === 'running') return 'wa-badge-brass';
    return 'wa-badge-muted';
}

export default function Index({ campaigns, flash }) {
    const { can } = useCan();
    const rows = campaigns.data || [];

    return (
        <WhatsAppPage
            title="حملات واتساب"
            subtitle="إرسال جماعي بإيقاع زمني ثابت أو عشوائي لحماية الأرقام."
            chips={[{ icon: 'ri-megaphone-line', label: 'الحملات', value: campaigns.total ?? rows.length }]}
            flash={flash}
            actions={
                can('admin.whatsapp-campaigns.create') ? (
                    <Link href={route('admin.whatsapp-campaigns.create')} className="wa-btn wa-btn-primary">
                        <i className="ri-megaphone-line" />
                        حملة جديدة
                    </Link>
                ) : null
            }
        >
            <WaPanel title="سجل الحملات" icon="ri-history-line">
                <div className="wa-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الرسالة</th>
                                <th>المستلمون</th>
                                <th>أُرسل</th>
                                <th>فشل</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((c) => (
                                <tr key={c.id}>
                                    <td>
                                        {can('admin.whatsapp-campaigns.show') ? (
                                            <Link
                                                href={route('admin.whatsapp-campaigns.show', c.id)}
                                                className="fw-semibold text-decoration-none"
                                                style={{ color: 'var(--wa-accent)' }}
                                            >
                                                {c.id}
                                            </Link>
                                        ) : (
                                            <span className="fw-semibold">{c.id}</span>
                                        )}
                                    </td>
                                    <td>{c.message_preview}</td>
                                    <td>{c.total_recipients}</td>
                                    <td>{c.sent_count}</td>
                                    <td>{c.failed_count}</td>
                                    <td>
                                        <span className={`wa-badge ${statusBadge(c.status)}`}>
                                            {c.status}
                                        </span>
                                    </td>
                                    <td dir="ltr">{c.created_at}</td>
                                </tr>
                            ))}
                            {rows.length === 0 && <TableEmptyRow colSpan={7}>لا توجد حملات.</TableEmptyRow>}
                        </tbody>
                    </table>
                </div>
            </WaPanel>
        </WhatsAppPage>
    );
}
