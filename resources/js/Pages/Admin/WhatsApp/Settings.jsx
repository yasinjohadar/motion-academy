import WhatsAppPage, { WaPanel } from '@/Components/Admin/WhatsAppPage';
import useCan from '@/hooks/useCan';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const PROVIDERS = [
    {
        value: 'evolution',
        label: 'Evolution API',
        hint: 'غير رسمي — نص حر في أي وقت، بلا قوالب. مناسب للحملات.',
        icon: 'ri-flashlight-line',
    },
    {
        value: 'meta',
        label: 'Meta Cloud API',
        hint: 'رسمي — خارج نافذة 24 ساعة لا يُقبل إلا قالب معتمد. مناسب لـ OTP.',
        icon: 'ri-verified-badge-line',
    },
    {
        value: 'flaxxa',
        label: 'Flaxxa Wapi',
        hint: 'رسمي عبر وسيط — رمز واحد بدل إعداد تطبيق Meta. بلا استقبال للرسائل الواردة.',
        icon: 'ri-cloud-line',
    },
];

/**
 * Each tab owns a set of fields. The map is what lets a hidden tab still announce
 * that it holds a validation error — without it, saving would appear to do nothing.
 */
const TABS = [
    { id: 'provider', label: 'المزوّد', icon: 'ri-server-line', fields: ['whatsapp_provider'] },
    { id: 'evolution', label: 'Evolution', icon: 'ri-flashlight-line', prefix: 'evolution_' },
    { id: 'meta', label: 'Meta Cloud API', icon: 'ri-verified-badge-line', prefix: 'meta_' },
    { id: 'flaxxa', label: 'Flaxxa Wapi', icon: 'ri-cloud-line', prefix: 'flaxxa_' },
    { id: 'routing', label: 'التوجيه', icon: 'ri-route-line', prefix: 'routing' },
    {
        id: 'pacing',
        label: 'إيقاع الإرسال',
        icon: 'ri-timer-flash-line',
        fields: [
            'delay_between_messages',
            'delay_between_broadcasts',
            'max_messages_per_minute',
            'random_delay_enabled',
            'min_delay',
            'max_delay',
        ],
    },
];

const tabOwnsField = (tab, field) =>
    tab.prefix ? field.startsWith(tab.prefix) : (tab.fields || []).includes(field);

export default function Settings({
    settings,
    webhookUrl,
    metaWebhookUrl,
    routing,
    purposes,
    providerCapabilities = {},
    rotationPoolCount,
    flash,
}) {
    const { can } = useCan();
    const editable = can('admin.whatsapp-settings.edit');
    const [tab, setTab] = useState('provider');
    const [templates, setTemplates] = useState(null);

    const { data, setData, put, processing, errors } = useForm({
        whatsapp_provider: settings.whatsapp_provider || 'evolution',

        evolution_base_url: settings.evolution_base_url || '',
        evolution_api_key: '',
        evolution_instance_name: settings.evolution_instance_name || '',
        evolution_rotation_enabled: Boolean(settings.evolution_rotation_enabled),
        evolution_webhook_secret: '',
        evolution_webhook_base_url: settings.evolution_webhook_base_url || '',

        meta_phone_number_id: settings.meta_phone_number_id || '',
        meta_waba_id: settings.meta_waba_id || '',
        meta_api_version: settings.meta_api_version || '',
        meta_access_token: '',
        meta_app_secret: '',
        meta_verify_token: '',

        flaxxa_base_url: settings.flaxxa_base_url || '',
        flaxxa_token: '',

        routing: routing || {},

        delay_between_messages: settings.delay_between_messages ?? 3,
        delay_between_broadcasts: settings.delay_between_broadcasts ?? 5,
        max_messages_per_minute: settings.max_messages_per_minute ?? 20,
        random_delay_enabled: Boolean(settings.random_delay_enabled),
        min_delay: settings.min_delay ?? 2,
        max_delay: settings.max_delay ?? 5,
    });

    const activeProvider = data.whatsapp_provider;
    const errorFields = Object.keys(errors);
    const errorCount = (candidate) => errorFields.filter((field) => tabOwnsField(candidate, field)).length;

    // A rejected save must never look like nothing happened: jump to the tab that
    // holds the problem, since the field itself may be on a hidden panel.
    useEffect(() => {
        if (errorFields.length === 0) {
            return;
        }

        const firstBroken = TABS.find((candidate) => errorCount(candidate) > 0);

        if (firstBroken && firstBroken.id !== tab) {
            setTab(firstBroken.id);
        }
    }, [errors]);

    const submit = (e) => {
        e.preventDefault();
        put(route('admin.whatsapp-settings.update'));
    };

    const test = (provider) => {
        router.post(
            route('admin.whatsapp-settings.test'),
            { provider, instance_name: data.evolution_instance_name },
            { preserveScroll: true },
        );
    };

    const setRouting = (purpose, path, value) => {
        const row = data.routing[purpose] || { provider: 'inherit', template: {} };

        const next =
            path === 'provider'
                ? { ...row, provider: value }
                : { ...row, template: { ...row.template, [path]: value } };

        setData('routing', { ...data.routing, [purpose]: next });
    };

    /** Which provider a purpose ends up on once `inherit` is resolved. */
    const effectiveProvider = (purpose) => {
        const chosen = data.routing[purpose]?.provider || 'inherit';

        return chosen === 'inherit' ? activeProvider : chosen;
    };

    /**
     * Whether a purpose on this provider is bound by Meta's 24-hour window, and so
     * needs an approved template. Read from the server's capability map rather than a
     * provider name — the check used to be `=== 'meta'` and silently missed Flaxxa.
     */
    const needsTemplate = (purpose) =>
        Boolean(providerCapabilities[effectiveProvider(purpose)]?.enforces_service_window);

    /** Providers that can hand us a list of approved template names. */
    const listableProvider = [...new Set((purposes || []).map((p) => effectiveProvider(p.value)))].find(
        (provider) => providerCapabilities[provider]?.supports_template_listing,
    );

    // Fetched only when the routing tab is opened: it is a network call against a
    // rate-limited API, and no other tab needs it.
    useEffect(() => {
        if (tab !== 'routing' || templates !== null || !listableProvider) {
            return;
        }

        let cancelled = false;

        fetch(route('admin.whatsapp-settings.templates', { provider: listableProvider }), {
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : { templates: [] }))
            .then((body) => {
                if (!cancelled) {
                    setTemplates(body.templates || []);
                }
            })
            // A failed lookup is not an error worth blocking on — the field falls back
            // to free text, which is what providers without listing always use.
            .catch(() => !cancelled && setTemplates([]));

        return () => {
            cancelled = true;
        };
    }, [tab, listableProvider, templates]);

    const copy = (value) => navigator.clipboard?.writeText(value || '');

    const providerBadge = (provider) =>
        activeProvider === provider ? <span className="wa-tab-badge">افتراضي</span> : null;

    return (
        <WhatsAppPage
            title="إعدادات واتساب"
            subtitle="اختيار المزوّد، بيانات الاتصال، وتوجيه كل غرض إلى المزوّد والقالب المناسبين."
            chips={[
                {
                    icon: 'ri-server-line',
                    label: 'المزوّد النشِط',
                    value: PROVIDERS.find((p) => p.value === activeProvider)?.label || activeProvider,
                },
                { icon: 'ri-refresh-line', label: 'أرقام التبديل', value: rotationPoolCount },
            ]}
            flash={flash}
        >
            <form onSubmit={submit}>
                <ul className="nav nav-tabs wa-tabs" role="tablist">
                    {TABS.map((item) => {
                        const broken = errorCount(item);

                        return (
                            <li className="nav-item" key={item.id}>
                                <button
                                    type="button"
                                    className={`nav-link${tab === item.id ? ' active' : ''}${
                                        broken ? ' is-broken' : ''
                                    }`}
                                    onClick={() => setTab(item.id)}
                                    aria-current={tab === item.id ? 'page' : undefined}
                                >
                                    <i className={item.icon} />
                                    {item.label}
                                    {broken > 0 && (
                                        <span className="wa-tab-error" title="يحتاج تصحيحاً">
                                            {broken}
                                        </span>
                                    )}
                                    {item.id === 'evolution' && providerBadge('evolution')}
                                    {item.id === 'meta' && providerBadge('meta')}
                                </button>
                            </li>
                        );
                    })}
                </ul>

                {tab === 'provider' && (
                    <WaPanel title="المزوّد الافتراضي" icon="ri-server-line">
                        <div className="row g-3">
                            {PROVIDERS.map((provider) => (
                                <div className="col-md-6" key={provider.value}>
                                    <label
                                        className={`wa-provider-card${
                                            activeProvider === provider.value ? ' is-active' : ''
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="whatsapp_provider"
                                            className="form-check-input"
                                            checked={activeProvider === provider.value}
                                            onChange={() => setData('whatsapp_provider', provider.value)}
                                            disabled={!editable}
                                        />
                                        <span className="wa-provider-body">
                                            <span className="wa-provider-title">
                                                <i className={provider.icon} />
                                                {provider.label}
                                            </span>
                                            <span className="wa-provider-hint">{provider.hint}</span>
                                        </span>
                                    </label>
                                </div>
                            ))}
                        </div>

                        {errors.whatsapp_provider && (
                            <div className="invalid-feedback d-block">{errors.whatsapp_provider}</div>
                        )}

                        <p className="wa-note">
                            هذا هو الافتراضي فقط. يمكن توجيه كل غرض إلى مزوّد مختلف من تبويب «التوجيه».
                        </p>
                    </WaPanel>
                )}

                {tab === 'evolution' && (
                    <WaPanel title="اتصال Evolution" icon="ri-links-line">
                        <div className="row g-3">
                            <div className="col-md-8">
                                <label className="form-label">Base URL</label>
                                <input
                                    className={`form-control${errors.evolution_base_url ? ' is-invalid' : ''}`}
                                    value={data.evolution_base_url}
                                    onChange={(e) => setData('evolution_base_url', e.target.value)}
                                    placeholder="https://evolution.example.com"
                                    dir="ltr"
                                />
                                {errors.evolution_base_url && (
                                    <div className="invalid-feedback d-block">{errors.evolution_base_url}</div>
                                )}
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">
                                    API Key {settings.has_api_key ? '(محفوظ — اتركه فارغاً للإبقاء)' : ''}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.evolution_api_key}
                                    onChange={(e) => setData('evolution_api_key', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                            </div>
                            <div className="col-md-6">
                                <label className="form-label">Instance الافتراضي</label>
                                <input
                                    className="form-control"
                                    value={data.evolution_instance_name}
                                    onChange={(e) => setData('evolution_instance_name', e.target.value)}
                                    dir="ltr"
                                />
                            </div>
                            <div className="col-md-6">
                                <label className="form-label">Webhook base URL (اختياري)</label>
                                <input
                                    className="form-control"
                                    value={data.evolution_webhook_base_url}
                                    onChange={(e) => setData('evolution_webhook_base_url', e.target.value)}
                                    dir="ltr"
                                    placeholder="https://your-domain.com"
                                />
                            </div>
                            <div className="col-12">
                                <label className="form-label">رابط Webhook</label>
                                <div className="input-group">
                                    <input className="form-control" value={webhookUrl || ''} readOnly dir="ltr" />
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary"
                                        onClick={() => copy(webhookUrl)}
                                    >
                                        <i className="ri-file-copy-line" />
                                    </button>
                                </div>
                            </div>
                            <div className="col-md-6">
                                <label className="form-label">
                                    Webhook Secret{' '}
                                    {settings.has_webhook_secret
                                        ? '(محفوظ — اتركه فارغاً للإبقاء)'
                                        : '(اختياري)'}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.evolution_webhook_secret}
                                    onChange={(e) => setData('evolution_webhook_secret', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                            </div>
                            <div className="col-md-6 d-flex align-items-end">
                                <div className="form-check">
                                    <input
                                        id="rotation"
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={data.evolution_rotation_enabled}
                                        onChange={(e) =>
                                            setData('evolution_rotation_enabled', e.target.checked)
                                        }
                                    />
                                    <label className="form-check-label" htmlFor="rotation">
                                        تفعيل التبديل بين الأرقام (حماية من الحظر)
                                    </label>
                                </div>
                            </div>
                        </div>

                        {editable && (
                            <div className="wa-actions">
                                <button
                                    type="button"
                                    className="wa-btn wa-btn-ghost"
                                    disabled={processing}
                                    onClick={() => test('evolution')}
                                >
                                    <i className="ri-wifi-line" />
                                    اختبار Evolution
                                </button>
                            </div>
                        )}
                    </WaPanel>
                )}

                {tab === 'meta' && (
                    <WaPanel title="اتصال Meta Cloud API" icon="ri-verified-badge-line">
                        <div className="row g-3">
                            <div className="col-md-4">
                                <label className="form-label">Phone Number ID</label>
                                <input
                                    className={`form-control${
                                        errors.meta_phone_number_id ? ' is-invalid' : ''
                                    }`}
                                    value={data.meta_phone_number_id}
                                    onChange={(e) => setData('meta_phone_number_id', e.target.value)}
                                    dir="ltr"
                                />
                                {errors.meta_phone_number_id && (
                                    <div className="invalid-feedback d-block">
                                        {errors.meta_phone_number_id}
                                    </div>
                                )}
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">WABA ID (اختياري)</label>
                                <input
                                    className="form-control"
                                    value={data.meta_waba_id}
                                    onChange={(e) => setData('meta_waba_id', e.target.value)}
                                    dir="ltr"
                                />
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">نسخة الواجهة</label>
                                <input
                                    className={`form-control${errors.meta_api_version ? ' is-invalid' : ''}`}
                                    value={data.meta_api_version}
                                    onChange={(e) => setData('meta_api_version', e.target.value)}
                                    placeholder="v21.0"
                                    dir="ltr"
                                />
                                {errors.meta_api_version ? (
                                    <div className="invalid-feedback d-block">{errors.meta_api_version}</div>
                                ) : (
                                    <div className="form-text">
                                        انسخها من لوحة تطبيقك في Meta — النسخ تُوقَف دورياً.
                                    </div>
                                )}
                            </div>

                            <div className="col-md-4">
                                <label className="form-label">
                                    Access Token{' '}
                                    {settings.has_meta_access_token ? '(محفوظ — اتركه فارغاً للإبقاء)' : ''}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.meta_access_token}
                                    onChange={(e) => setData('meta_access_token', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">
                                    App Secret {settings.has_meta_app_secret ? '(محفوظ)' : ''}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.meta_app_secret}
                                    onChange={(e) => setData('meta_app_secret', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                                <div className="form-text">
                                    بدونه تُرفض كل رسائل الـ webhook الواردة — التوقيع لا يمكن التحقق منه.
                                </div>
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">
                                    Verify Token {settings.has_meta_verify_token ? '(محفوظ)' : ''}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.meta_verify_token}
                                    onChange={(e) => setData('meta_verify_token', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                                <div className="form-text">اكتب نصاً من عندك، ثم الصقه نفسه في لوحة Meta.</div>
                            </div>

                            <div className="col-12">
                                <label className="form-label">Callback URL لـ Meta</label>
                                <div className="input-group">
                                    <input
                                        className="form-control"
                                        value={metaWebhookUrl || ''}
                                        readOnly
                                        dir="ltr"
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary"
                                        onClick={() => copy(metaWebhookUrl)}
                                    >
                                        <i className="ri-file-copy-line" />
                                    </button>
                                </div>
                                <div className="form-text">
                                    الصقه في Webhooks داخل تطبيق Meta، واشترك في حقل <code>messages</code>.
                                </div>
                            </div>
                        </div>

                        {editable && (
                            <div className="wa-actions">
                                <button
                                    type="button"
                                    className="wa-btn wa-btn-ghost"
                                    disabled={processing}
                                    onClick={() => test('meta')}
                                >
                                    <i className="ri-wifi-line" />
                                    اختبار Meta
                                </button>
                            </div>
                        )}
                    </WaPanel>
                )}

                {tab === 'flaxxa' && (
                    <WaPanel title="اتصال Flaxxa Wapi" icon="ri-cloud-line">
                        <div className="row g-3">
                            <div className="col-md-8">
                                <label className="form-label">Base URL</label>
                                <input
                                    className={`form-control${errors.flaxxa_base_url ? ' is-invalid' : ''}`}
                                    value={data.flaxxa_base_url}
                                    onChange={(e) => setData('flaxxa_base_url', e.target.value)}
                                    placeholder="https://wapi.flaxxa.com"
                                    dir="ltr"
                                />
                                {errors.flaxxa_base_url && (
                                    <div className="invalid-feedback d-block">{errors.flaxxa_base_url}</div>
                                )}
                            </div>
                            <div className="col-md-4">
                                <label className="form-label">
                                    API Token{' '}
                                    {settings.has_flaxxa_token ? '(محفوظ — اتركه فارغاً للإبقاء)' : ''}
                                </label>
                                <input
                                    type="password"
                                    className="form-control"
                                    value={data.flaxxa_token}
                                    onChange={(e) => setData('flaxxa_token', e.target.value)}
                                    autoComplete="new-password"
                                    dir="ltr"
                                />
                                <div className="form-text">
                                    من لوحة Flaxxa: <span dir="ltr">Brand → API Access</span>. الرمز مرتبط
                                    بعلامة تجارية ورقم واحد.
                                </div>
                            </div>

                            <div className="col-12">
                                <div className="alert alert-warning mb-0">
                                    <div className="fw-semibold mb-1">
                                        <i className="ri-error-warning-line me-1" />
                                        قيدان يجب معرفتهما قبل الاعتماد عليه
                                    </div>
                                    <ul className="mb-0 ps-3">
                                        <li>
                                            <strong>لا يستقبل الرسائل الواردة.</strong> لا توثّق Flaxxa أي
                                            webhook، فردود العملاء لن تصل إلى صندوق المحادثات، ولا يمكن معرفة
                                            حالة نافذة الـ24 ساعة. لذلك كل رسالة يبدأها النظام (رموز التحقق،
                                            إعادة كلمة المرور، الحملات) <strong>تحتاج قالباً معتمداً</strong>،
                                            بينما يمرّ ردّ المشرف اليدوي نصّاً حرّاً.
                                        </li>
                                        <li>
                                            <strong>حالة التسليم بالاستعلام لا بالإشعار.</strong> يسأل النظام
                                            عن كل رسالة بعد إرسالها، وكل سؤال يستهلك من حدّ 60 طلباً في
                                            الدقيقة. أوقف <code>FLAXXA_POLL_STATUS</code> قبل الحملات الكبيرة.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {editable && (
                            <div className="wa-actions">
                                <button
                                    type="button"
                                    className="wa-btn wa-btn-ghost"
                                    disabled={processing}
                                    onClick={() => test('flaxxa')}
                                >
                                    <i className="ri-wifi-line" />
                                    اختبار Flaxxa
                                </button>
                            </div>
                        )}
                    </WaPanel>
                )}

                {tab === 'routing' && (
                    <WaPanel title="توجيه الرسائل حسب الغرض" icon="ri-route-line">
                        <p className="wa-note">
                            كل غرض يمكن أن يسلك مزوّداً مختلفاً. «افتراضي» يتبع المزوّد المختار في تبويب
                            «المزوّد». حقل القالب يُفعَّل فقط للمزوّدات الرسمية التي تخضع لنافذة الـ24 ساعة.
                            {listableProvider && templates === null && ' — جارٍ جلب القوالب المعتمدة…'}
                            {listableProvider && templates?.length === 0 &&
                                ' — تعذّر جلب القوالب، اكتب اسم القالب يدوياً.'}
                        </p>

                        <div className="table-responsive">
                            <table className="table align-middle admin-table">
                                <thead>
                                    <tr>
                                        <th style={{ width: '32%' }}>الغرض</th>
                                        <th style={{ width: '20%' }}>المزوّد</th>
                                        <th>اسم القالب المعتمد</th>
                                        <th style={{ width: '14%' }}>لغة القالب</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(purposes || []).map((purpose) => {
                                        const row = data.routing[purpose.value] || {
                                            provider: 'inherit',
                                            template: {},
                                        };
                                        const windowed = needsTemplate(purpose.value);
                                        const templateMissing =
                                            windowed && purpose.business_initiated && !row.template?.name;

                                        return (
                                            <tr key={purpose.value}>
                                                <td>
                                                    <div className="fw-semibold">{purpose.label}</div>
                                                    <div className="text-muted small">
                                                        {purpose.description}
                                                    </div>
                                                </td>
                                                <td>
                                                    <select
                                                        className="form-select form-select-sm"
                                                        value={row.provider || 'inherit'}
                                                        onChange={(e) =>
                                                            setRouting(
                                                                purpose.value,
                                                                'provider',
                                                                e.target.value,
                                                            )
                                                        }
                                                        disabled={!editable}
                                                    >
                                                        <option value="inherit">افتراضي</option>
                                                        <option value="evolution">Evolution</option>
                                                        <option value="meta">Meta</option>
                                                        <option value="flaxxa">Flaxxa</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    {templates?.length ? (
                                                        <select
                                                            className={`form-select form-select-sm${
                                                                templateMissing ? ' is-invalid' : ''
                                                            }`}
                                                            value={row.template?.name || ''}
                                                            onChange={(e) =>
                                                                setRouting(
                                                                    purpose.value,
                                                                    'name',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            disabled={!editable || !windowed}
                                                        >
                                                            <option value="">— بلا قالب —</option>
                                                            {templates.map((template) => (
                                                                <option
                                                                    key={template.name}
                                                                    value={template.name}
                                                                >
                                                                    {template.name}
                                                                    {template.category
                                                                        ? ` · ${template.category}`
                                                                        : ''}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <input
                                                            className={`form-control form-control-sm${
                                                                templateMissing ? ' is-invalid' : ''
                                                            }`}
                                                            value={row.template?.name || ''}
                                                            onChange={(e) =>
                                                                setRouting(
                                                                    purpose.value,
                                                                    'name',
                                                                    e.target.value,
                                                                )
                                                            }
                                                            placeholder={
                                                                windowed ? 'مثال: otp_code' : '— غير مطلوب —'
                                                            }
                                                            disabled={!editable || !windowed}
                                                            dir="ltr"
                                                        />
                                                    )}
                                                    {templateMissing && (
                                                        <div className="invalid-feedback d-block">
                                                            هذا الغرض يبدأ المحادثة، وMeta ترفضه بلا قالب
                                                            معتمد.
                                                        </div>
                                                    )}
                                                </td>
                                                <td>
                                                    <input
                                                        className="form-control form-control-sm"
                                                        value={row.template?.language || 'ar'}
                                                        onChange={(e) =>
                                                            setRouting(
                                                                purpose.value,
                                                                'language',
                                                                e.target.value,
                                                            )
                                                        }
                                                        disabled={!editable || !windowed}
                                                        dir="ltr"
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </WaPanel>
                )}

                {tab === 'pacing' && (
                    <WaPanel title="إيقاع الإرسال" icon="ri-timer-flash-line">
                        <div className="row g-3">
                            <div className="col-md-3">
                                <label className="form-label">ثواني بين الرسائل</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    value={data.delay_between_messages}
                                    onChange={(e) =>
                                        setData('delay_between_messages', Number(e.target.value))
                                    }
                                />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">أدنى عشوائي</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    value={data.min_delay}
                                    onChange={(e) => setData('min_delay', Number(e.target.value))}
                                />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">أعلى عشوائي</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    value={data.max_delay}
                                    onChange={(e) => setData('max_delay', Number(e.target.value))}
                                />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">حد في الدقيقة (مرجع)</label>
                                <input
                                    type="number"
                                    className="form-control"
                                    value={data.max_messages_per_minute}
                                    onChange={(e) =>
                                        setData('max_messages_per_minute', Number(e.target.value))
                                    }
                                />
                            </div>
                            <div className="col-12">
                                <div className="form-check">
                                    <input
                                        id="random"
                                        type="checkbox"
                                        className="form-check-input"
                                        checked={data.random_delay_enabled}
                                        onChange={(e) => setData('random_delay_enabled', e.target.checked)}
                                    />
                                    <label className="form-check-label" htmlFor="random">
                                        تفعيل الفارق العشوائي
                                    </label>
                                </div>
                            </div>
                        </div>
                    </WaPanel>
                )}

                {editable && (
                    <div className="wa-savebar">
                        <span className="wa-savebar__hint">
                            الحفظ يشمل كل التبويبات — تنقّل بينها بحرية قبل الحفظ.
                        </span>
                        <button type="submit" className="wa-btn wa-btn-primary" disabled={processing}>
                            <i className="ri-save-3-line" />
                            حفظ الإعدادات
                        </button>
                    </div>
                )}
            </form>
        </WhatsAppPage>
    );
}
