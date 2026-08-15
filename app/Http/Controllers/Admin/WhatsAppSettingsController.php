<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WhatsAppPurpose;
use App\Http\Controllers\Controller;
use App\Models\EvolutionInstance;
use App\Services\WhatsApp\Evolution\EvolutionService;
use App\Services\WhatsApp\ProviderCapabilities;
use App\Services\WhatsApp\WhatsAppProviderFactory;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingsController extends Controller implements HasMiddleware
{
    public function __construct(
        private WhatsAppSettingsService $settingsService,
        private EvolutionService $evolutionService,
    ) {}

    public static function middleware(): array
    {
        return [
            (new Middleware('permission:admin.whatsapp-settings.list|admin.whatsapp-settings.edit'))->only(['index', 'templates']),
            (new Middleware('permission:admin.whatsapp-settings.edit'))->only(['update', 'test']),
        ];
    }

    public function index(Request $request): Response
    {
        $this->settingsService->initializeDefaults();

        return Inertia::render('Admin/WhatsApp/Settings', [
            'settings' => $this->settingsService->toAdminArray(),
            'webhookUrl' => $this->evolutionService->webhookUrl(),
            'metaWebhookUrl' => route('api.webhooks.meta'),
            'routing' => $this->routingForDisplay(),
            'purposes' => array_map(fn (WhatsAppPurpose $purpose): array => [
                'value' => $purpose->value,
                'label' => $purpose->label(),
                'description' => $purpose->description(),
                'business_initiated' => $purpose->isBusinessInitiated(),
            ], WhatsAppPurpose::cases()),
            // The UI's "this purpose needs a template" warning must follow the capability,
            // not a provider name — it was tied to Meta and would have missed Flaxxa.
            'providerCapabilities' => collect(WhatsAppSettingsService::SUPPORTED_PROVIDERS)
                ->mapWithKeys(function (string $provider): array {
                    $capabilities = ProviderCapabilities::for($provider);

                    return [$provider => [
                        'enforces_service_window' => $capabilities->enforcesServiceWindow,
                        'supports_template_listing' => $capabilities->exposesTemplateCatalog,
                    ]];
                })
                ->all(),
            'rotationPoolCount' => EvolutionInstance::rotationPoolCount(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_provider' => ['required', Rule::in(WhatsAppSettingsService::SUPPORTED_PROVIDERS)],

            // Only required for the provider actually in use — otherwise a Meta-only
            // installation could never save its settings at all.
            'evolution_base_url' => ['required_if:whatsapp_provider,evolution', 'nullable', 'string', 'max:500'],
            'evolution_api_key' => ['nullable', 'string', 'max:500'],
            'evolution_instance_name' => ['nullable', 'string', 'max:150'],
            'evolution_rotation_enabled' => ['nullable', 'boolean'],
            'evolution_webhook_secret' => ['nullable', 'string', 'max:500'],
            'evolution_webhook_base_url' => ['nullable', 'string', 'max:500'],
            'delay_between_messages' => ['required', 'integer', 'min:0', 'max:300'],
            'delay_between_broadcasts' => ['required', 'integer', 'min:0', 'max:600'],
            'max_messages_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'random_delay_enabled' => ['nullable', 'boolean'],
            'min_delay' => ['required', 'integer', 'min:0', 'max:120'],
            'max_delay' => ['required', 'integer', 'min:0', 'max:120'],

            'meta_phone_number_id' => ['required_if:whatsapp_provider,meta', 'nullable', 'string', 'max:100'],
            'meta_waba_id' => ['nullable', 'string', 'max:100'],
            'meta_access_token' => ['nullable', 'string', 'max:1000'],
            'meta_api_version' => ['nullable', 'string', 'max:10', 'regex:/^v\d+\.\d+$/'],
            'meta_app_secret' => ['nullable', 'string', 'max:500'],
            'meta_verify_token' => ['nullable', 'string', 'max:500'],

            'flaxxa_base_url' => ['required_if:whatsapp_provider,flaxxa', 'nullable', 'string', 'max:500'],
            'flaxxa_token' => ['nullable', 'string', 'max:500'],

            'routing' => ['nullable', 'array'],
            'routing.*.provider' => ['nullable', Rule::in([...WhatsAppSettingsService::SUPPORTED_PROVIDERS, 'inherit'])],
            'routing.*.template.name' => ['nullable', 'string', 'max:150'],
            'routing.*.template.language' => ['nullable', 'string', 'max:10'],
        ], [
            'meta_phone_number_id.required_if' => 'معرّف رقم الهاتف (Phone Number ID) مطلوب لمزوّد Meta.',
            'evolution_base_url.required_if' => 'رابط خدمة Evolution مطلوب لمزوّد Evolution.',
            'flaxxa_base_url.required_if' => 'رابط خدمة Flaxxa مطلوب لمزوّد Flaxxa.',
            'meta_api_version.regex' => 'نسخة الواجهة تُكتب بالشكل v21.0 كما تظهر في لوحة Meta.',
        ]);

        $existing = $this->settingsService->getSettings();

        // `ConvertEmptyStringsToNull` blanks optional fields before validation, and a
        // nullable rule then leaves the key out of $validated entirely — so fill the
        // optional keys in rather than guarding every read below.
        $validated += array_fill_keys([
            'evolution_base_url', 'evolution_api_key', 'evolution_instance_name',
            'evolution_webhook_secret', 'evolution_webhook_base_url',
            'meta_phone_number_id', 'meta_waba_id', 'meta_access_token',
            'meta_api_version', 'meta_app_secret', 'meta_verify_token',
            'flaxxa_base_url', 'flaxxa_token',
        ], '');

        // Secrets are write-only in the UI: an empty field means "leave it alone",
        // because the current value is never sent to the browser to begin with.
        foreach ([
            'evolution_api_key', 'evolution_webhook_secret',
            'meta_access_token', 'meta_app_secret', 'meta_verify_token',
            'flaxxa_token',
        ] as $secret) {
            if (empty($validated[$secret])) {
                $validated[$secret] = $existing[$secret];
            }
        }

        if ($request->filled('routing')) {
            $this->settingsService->updateRoutingOverrides($this->sanitizeRouting($validated['routing'] ?? []));
        }

        $this->settingsService->updateSettings([
            'whatsapp_provider' => $validated['whatsapp_provider'],
            'meta_phone_number_id' => $validated['meta_phone_number_id'],
            'meta_waba_id' => $validated['meta_waba_id'],
            'meta_access_token' => $validated['meta_access_token'],
            'meta_api_version' => $validated['meta_api_version'] ?: WhatsAppSettingsService::DEFAULT_META_API_VERSION,
            'meta_app_secret' => $validated['meta_app_secret'],
            'meta_verify_token' => $validated['meta_verify_token'],
            'evolution_base_url' => $validated['evolution_base_url'],
            'evolution_api_key' => $validated['evolution_api_key'],
            'evolution_instance_name' => $validated['evolution_instance_name'],
            'evolution_rotation_enabled' => $request->boolean('evolution_rotation_enabled'),
            'evolution_webhook_secret' => $validated['evolution_webhook_secret'],
            'evolution_webhook_base_url' => $validated['evolution_webhook_base_url'],
            'flaxxa_base_url' => $validated['flaxxa_base_url'] ?: WhatsAppSettingsService::DEFAULT_FLAXXA_BASE_URL,
            'flaxxa_token' => $validated['flaxxa_token'],
            'delay_between_messages' => $validated['delay_between_messages'],
            'delay_between_broadcasts' => $validated['delay_between_broadcasts'],
            'max_messages_per_minute' => $validated['max_messages_per_minute'],
            'random_delay_enabled' => $request->boolean('random_delay_enabled'),
            'min_delay' => $validated['min_delay'],
            'max_delay' => max($validated['min_delay'], $validated['max_delay']),
            'whatsapp_enabled' => true,
        ]);

        if ($validated['whatsapp_provider'] === 'evolution' && ! empty($validated['evolution_instance_name'])) {
            try {
                $this->evolutionService->registerManualInstance([
                    'instance_name' => $validated['evolution_instance_name'],
                    'verify' => false,
                    'set_as_default' => true,
                ]);
            } catch (\Throwable) {
                // non-blocking
            }
        }

        return redirect()
            ->route('admin.whatsapp-settings.index')
            ->with('success', 'تم حفظ إعدادات واتساب.');
    }

    /**
     * Test whichever provider the operator asked about — not always Evolution.
     * Either can be verified without switching the live provider over to it first.
     */
    public function test(Request $request): RedirectResponse
    {
        $settings = $this->settingsService->getSettings();

        $target = $request->input('provider') ?: $settings['whatsapp_provider'];

        if (! in_array($target, WhatsAppSettingsService::SUPPORTED_PROVIDERS, true)) {
            return back()->with('error', 'مزوّد غير معروف.');
        }

        $config = $this->settingsService->getProviderConfig($target);

        if ($target === 'evolution') {
            $instance = $request->input('instance_name') ?: ($settings['evolution_instance_name'] ?? '');

            if ($instance === '') {
                return back()->with('error', 'حدد اسم Instance للاختبار.');
            }

            $config['instance_name'] = $instance;
        } elseif ($target === 'meta' && ($config['phone_number_id'] === '' || $config['access_token'] === '')) {
            return back()->with('error', 'أدخل معرّف رقم الهاتف ورمز الوصول من Meta أولاً، ثم احفظ.');
        } elseif ($target === 'flaxxa' && $config['token'] === '') {
            return back()->with('error', 'أدخل رمز Flaxxa أولاً، ثم احفظ.');
        }

        try {
            $result = WhatsAppProviderFactory::create($target, $config)->testConnection();
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذّر الاتصال: '.$e->getMessage());
        }

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message'] ?? 'انتهى الاختبار.'
        );
    }

    /**
     * Approved templates for the routing matrix's dropdown.
     *
     * Fetched on demand rather than with the page: it is a network call against a
     * rate-limited API, and only the routing tab needs it. Providers that expose no
     * template listing simply return an empty list, and the UI falls back to a text
     * field — which is the behaviour Meta users already have.
     */
    public function templates(Request $request): JsonResponse
    {
        $provider = $request->input('provider') ?: $this->settingsService->getSettings()['whatsapp_provider'];

        if (! in_array($provider, WhatsAppSettingsService::SUPPORTED_PROVIDERS, true)) {
            return response()->json(['templates' => []]);
        }

        if (! ProviderCapabilities::for($provider)->exposesTemplateCatalog) {
            return response()->json(['templates' => []]);
        }

        $instance = WhatsAppProviderFactory::create($provider, $this->settingsService->getProviderConfig($provider));

        if (! method_exists($instance, 'listTemplates') || ! $instance->isConfigured()) {
            return response()->json(['templates' => []]);
        }

        try {
            // Short cache: enough to survive tab switching without pinning a stale list
            // after the operator gets a template approved.
            $templates = Cache::remember(
                "whatsapp:templates:{$provider}",
                now()->addMinutes(5),
                fn (): array => $instance->listTemplates(),
            );
        } catch (\Throwable $e) {
            // A dead provider must not break the settings screen — the field degrades
            // to free text and the operator can still save.
            return response()->json(['templates' => [], 'error' => $e->getMessage()]);
        }

        return response()->json(['templates' => $templates]);
    }

    /**
     * The routing matrix as the UI needs it: one row per purpose, with the stored
     * override falling back to the config default.
     *
     * @return array<string, array{provider: string, template: array{name: ?string, language: string}}>
     */
    protected function routingForDisplay(): array
    {
        $stored = $this->settingsService->getRoutingOverrides();
        $rows = [];

        foreach (WhatsAppPurpose::cases() as $purpose) {
            $default = config("whatsapp.routing.{$purpose->value}", []);
            $override = $stored[$purpose->value] ?? [];

            $rows[$purpose->value] = [
                'provider' => $override['provider'] ?? $default['provider'] ?? 'inherit',
                'template' => [
                    'name' => $override['template']['name'] ?? $default['template']['name'] ?? null,
                    'language' => $override['template']['language'] ?? $default['template']['language'] ?? 'ar',
                ],
            ];
        }

        return $rows;
    }

    /**
     * Keep only known purposes and known keys — the matrix is submitted as a nested
     * array and must not become a way to write arbitrary settings.
     *
     * @param  array<string, mixed>  $routing
     * @return array<string, array{provider: string, template: array{name: ?string, language: string}}>
     */
    protected function sanitizeRouting(array $routing): array
    {
        $clean = [];

        foreach (WhatsAppPurpose::cases() as $purpose) {
            $row = $routing[$purpose->value] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['template']['name'] ?? ''));

            $clean[$purpose->value] = [
                'provider' => $row['provider'] ?? 'inherit',
                'template' => [
                    'name' => $name !== '' ? $name : null,
                    'language' => trim((string) ($row['template']['language'] ?? 'ar')) ?: 'ar',
                ],
            ];
        }

        return $clean;
    }
}
