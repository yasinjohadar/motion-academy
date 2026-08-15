import AdminUiPage, { AdminPanel } from '@/Components/Admin/AdminUiPage';

export default function WhatsAppPage({ eyebrow = 'WhatsApp Evolution', ...props }) {
    return <AdminUiPage eyebrow={eyebrow} eyebrowIcon="ri-whatsapp-line" {...props} />;
}

export function WaPanel(props) {
    return <AdminPanel {...props} />;
}
