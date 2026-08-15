import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import { Link, useForm } from '@inertiajs/react';

export default function Create({ users, delaySettings, flash }) {
    const { data, setData, post, processing, errors } = useForm({
        message: '',
        user_ids: [],
        manual_phones: '',
    });

    const toggleUser = (id) => {
        const set = new Set(data.user_ids);
        if (set.has(id)) {
            set.delete(id);
        } else {
            set.add(id);
        }
        setData('user_ids', [...set]);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.whatsapp-campaigns.store'));
    };

    const delayLabel = delaySettings.random_delay_enabled
        ? `${delaySettings.delay_between_messages}ث + عشوائي ${delaySettings.min_delay}-${delaySettings.max_delay}ث`
        : `${delaySettings.delay_between_messages}ث ثابت`;

    return (
        <WhatsAppPage
            title="حملة جديدة"
            subtitle="اختر الجمهور، اكتب الرسالة، ودع النظام يجدول الإرسال بإيقاع آمن."
            chips={[{ icon: 'ri-timer-flash-line', label: 'إيقاع الإرسال', value: delayLabel }]}
            flash={flash}
            actions={
                <Link href={route('admin.whatsapp-campaigns.index')} className="wa-btn wa-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة
                </Link>
            }
        >
            <form onSubmit={submit}>
                <WaPanel title="نص الحملة" icon="ri-chat-quote-line">
                    <label className="form-label">نص الرسالة</label>
                    <textarea
                        className={`form-control${errors.message ? ' is-invalid' : ''}`}
                        rows={4}
                        value={data.message}
                        onChange={(e) => setData('message', e.target.value)}
                        placeholder="مرحباً {name}"
                        required
                    />
                    {errors.message && <div className="invalid-feedback d-block">{errors.message}</div>}
                    <div className="form-text mt-2">
                        يمكنك استخدام {'{name}'} {'{phone}'} {'{email}'}
                    </div>
                </WaPanel>

                <WaPanel title="الجمهور" icon="ri-group-line">
                    <div className="mb-3">
                        <label className="form-label">مستخدمو النظام</label>
                        <div className="wa-user-list">
                            {users.length === 0 && (
                                <div className="text-muted small py-2">لا يوجد مستخدمون برقم هاتف.</div>
                            )}
                            {users.map((u) => (
                                <label key={u.id} className="d-flex gap-2 align-items-center">
                                    <input
                                        type="checkbox"
                                        className="form-check-input m-0"
                                        checked={data.user_ids.includes(u.id)}
                                        onChange={() => toggleUser(u.id)}
                                    />
                                    <span>
                                        {u.name}{' '}
                                        <small className="text-muted" dir="ltr">
                                            {u.phone}
                                        </small>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <label className="form-label">أرقام يدوية</label>
                    <textarea
                        className="form-control"
                        rows={4}
                        value={data.manual_phones}
                        onChange={(e) => setData('manual_phones', e.target.value)}
                        placeholder={'9665xxxxxxxx\n9665yyyyyyyy'}
                        dir="ltr"
                    />
                    <div className="form-text mt-2">سطر لكل رقم أو مفصولة بفواصل</div>

                    <div className="wa-actions">
                        <button type="submit" className="wa-btn wa-btn-primary" disabled={processing}>
                            <i className="ri-calendar-schedule-line" />
                            جدولة الحملة
                        </button>
                    </div>
                </WaPanel>
            </form>
        </WhatsAppPage>
    );
}
