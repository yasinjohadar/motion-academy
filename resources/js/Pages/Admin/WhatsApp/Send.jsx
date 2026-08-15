import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import { useForm } from '@inertiajs/react';

export default function Send({ instanceName, rotationActive, rotationPoolCount, flash }) {
    const { data, setData, post, processing, errors } = useForm({
        to: '',
        text: '',
        sync: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('admin.whatsapp-send.store'));
    };

    return (
        <WhatsAppPage
            title="إرسال رسالة"
            subtitle="أرسل رسالة فورية عبر Evolution مع احترام إعدادات التبديل."
            chips={[
                { icon: 'ri-server-line', label: 'Instance', value: instanceName || '—' },
                {
                    icon: 'ri-refresh-line',
                    label: rotationActive ? `تبديل نشط (${rotationPoolCount})` : 'تبديل متوقف',
                },
            ]}
            flash={flash}
        >
            <WaPanel title="رسالة جديدة" icon="ri-send-plane-2-line">
                <form onSubmit={submit}>
                    <div className="mb-3">
                        <label className="form-label">رقم المستلم</label>
                        <input
                            className={`form-control${errors.to ? ' is-invalid' : ''}`}
                            value={data.to}
                            onChange={(e) => setData('to', e.target.value)}
                            placeholder="9665xxxxxxxx"
                            dir="ltr"
                            required
                        />
                        {errors.to && <div className="invalid-feedback d-block">{errors.to}</div>}
                    </div>
                    <div className="mb-3">
                        <label className="form-label">نص الرسالة</label>
                        <textarea
                            className={`form-control${errors.text ? ' is-invalid' : ''}`}
                            rows={5}
                            value={data.text}
                            onChange={(e) => setData('text', e.target.value)}
                            required
                        />
                        {errors.text && <div className="invalid-feedback d-block">{errors.text}</div>}
                    </div>
                    <div className="wa-actions">
                        <button type="submit" className="wa-btn wa-btn-primary" disabled={processing}>
                            <i className="ri-send-plane-fill" />
                            إرسال
                        </button>
                    </div>
                </form>
            </WaPanel>
        </WhatsAppPage>
    );
}
