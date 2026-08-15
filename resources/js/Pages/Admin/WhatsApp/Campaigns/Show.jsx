import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import { Link } from '@inertiajs/react';

function statusBadge(status) {
    if (status === 'sent' || status === 'delivered' || status === 'completed') return 'wa-badge-ok';
    if (status === 'failed') return 'wa-badge-muted';
    if (status === 'pending' || status === 'queued' || status === 'processing') return 'wa-badge-brass';
    return 'wa-badge-muted';
}

export default function Show({ campaign, flash }) {
    return (
        <WhatsAppPage
            title={`حملة #${campaign.id}`}
            subtitle="تفاصيل القالب وحالة كل مستلم في الحملة."
            chips={[
                { icon: 'ri-pulse-line', label: 'الحالة', value: campaign.status },
                {
                    icon: 'ri-mail-send-line',
                    label: 'أُرسل',
                    value: `${campaign.sent_count}/${campaign.total_recipients}`,
                },
                { icon: 'ri-error-warning-line', label: 'فشل', value: campaign.failed_count },
            ]}
            flash={flash}
            actions={
                <Link href={route('admin.whatsapp-campaigns.index')} className="wa-btn wa-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة
                </Link>
            }
        >
            <WaPanel title="قالب الرسالة" icon="ri-file-text-line">
                <pre className="wa-message-preview">{campaign.message_template}</pre>
            </WaPanel>

            <WaPanel title="المستلمون" icon="ri-contacts-book-line">
                <div className="wa-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>الهاتف</th>
                                <th>المستخدم</th>
                                <th>الحالة</th>
                                <th>خطأ</th>
                                <th>وقت الإرسال</th>
                            </tr>
                        </thead>
                        <tbody>
                            {campaign.recipients.map((r) => (
                                <tr key={r.id}>
                                    <td dir="ltr">{r.phone}</td>
                                    <td>{r.user || '—'}</td>
                                    <td>
                                        <span className={`wa-badge ${statusBadge(r.status)}`}>{r.status}</span>
                                    </td>
                                    <td className="small" style={{ color: 'var(--wa-danger)' }}>
                                        {r.error_message || ''}
                                    </td>
                                    <td dir="ltr">{r.sent_at || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </WaPanel>
        </WhatsAppPage>
    );
}
