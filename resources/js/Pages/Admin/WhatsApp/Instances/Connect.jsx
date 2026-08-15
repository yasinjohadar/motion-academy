import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import { Link, router } from '@inertiajs/react';

export default function Connect({ instance, flash }) {
    const isOpen = instance.connection_status === 'open';

    return (
        <WhatsAppPage
            title="ربط الرقم"
            subtitle="امسح رمز QR من تطبيق واتساب لربط الـ instance."
            chips={[
                { icon: 'ri-smartphone-line', label: instance.instance_name },
                {
                    icon: isOpen ? 'ri-checkbox-circle-line' : 'ri-qr-code-line',
                    label: instance.connection_status || 'unknown',
                },
            ]}
            flash={flash}
            actions={
                <Link href={route('admin.whatsapp-instances.index')} className="wa-btn wa-btn-ghost">
                    <i className="ri-arrow-right-line" />
                    العودة
                </Link>
            }
        >
            <WaPanel title="رمز الاتصال" icon="ri-qr-code-line">
                <div className="text-center">
                    <p className="mb-3">
                        الحالة:{' '}
                        <span className={`wa-badge ${isOpen ? 'wa-badge-ok' : 'wa-badge-muted'}`}>
                            {instance.connection_status}
                        </span>
                    </p>

                    {instance.qr_code ? (
                        <div className="wa-qr mb-3">
                            <img
                                src={
                                    String(instance.qr_code).startsWith('data:')
                                        ? instance.qr_code
                                        : `data:image/png;base64,${instance.qr_code}`
                                }
                                alt="QR"
                            />
                        </div>
                    ) : (
                        <p className="text-muted mb-3">لا يوجد QR محفوظ. اطلب رمزاً جديداً.</p>
                    )}

                    <div className="wa-actions justify-content-center border-0 pt-0 mt-0">
                        <button
                            type="button"
                            className="wa-btn wa-btn-primary"
                            onClick={() =>
                                router.post(
                                    route('admin.whatsapp-instances.qr', instance.instance_name),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <i className="ri-qr-code-line" />
                            جلب QR
                        </button>
                        <button
                            type="button"
                            className="wa-btn wa-btn-ghost"
                            onClick={() =>
                                router.post(
                                    route('admin.whatsapp-instances.refresh', instance.instance_name),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <i className="ri-refresh-line" />
                            تحديث الحالة
                        </button>
                    </div>
                </div>
            </WaPanel>
        </WhatsAppPage>
    );
}
