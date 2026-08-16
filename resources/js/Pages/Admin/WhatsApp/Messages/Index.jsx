import TableEmptyRow from '@/Components/Admin/TableEmptyRow';
import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';

export default function Index({ messages, flash }) {
    const rows = messages.data || [];

    return (
        <WhatsAppPage
            title="سجل الرسائل"
            subtitle="الصادر والوارد عبر Evolution — تتبع كامل للحالة والـ instance."
            chips={[{ icon: 'ri-chat-history-line', label: 'الرسائل', value: messages.total ?? rows.length }]}
            flash={flash}
        >
            <WaPanel title="آخر النشاط" icon="ri-inbox-archive-line">
                <div className="wa-table-wrap">
                    <table className="admin-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاتجاه</th>
                                <th>المستلم/المرسل</th>
                                <th>النص</th>
                                <th>الحالة</th>
                                <th>Instance</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((m) => (
                                <tr key={m.id}>
                                    <td>{m.id}</td>
                                    <td>
                                        <span
                                            className={`wa-badge ${
                                                m.direction === 'outbound' ? 'wa-badge-brass' : 'wa-badge-ok'
                                            }`}
                                        >
                                            {m.direction === 'outbound' ? 'صادر' : 'وارد'}
                                        </span>
                                    </td>
                                    <td dir="ltr">{m.contact || '—'}</td>
                                    <td>{m.body}</td>
                                    <td>
                                        <span className="wa-badge wa-badge-muted">{m.status}</span>
                                    </td>
                                    <td dir="ltr">{m.instance || '—'}</td>
                                    <td dir="ltr">{m.created_at}</td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <TableEmptyRow colSpan={7}>
                                    لا رسائل بعد.
                                </TableEmptyRow>
                            )}
                        </tbody>
                    </table>
                </div>
            </WaPanel>
        </WhatsAppPage>
    );
}
