<?php

namespace App\Modules\WhatsApp\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Services\Evolution\EvolutionApiException;
use App\Modules\WhatsApp\Services\Evolution\EvolutionService;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsAppInstanceController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(
        private EvolutionService $evolutionService,
        private WhatsAppSettingsService $settingsService,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'whatsapp-instances',
            false,
            [
                'admin.whatsapp-instances.edit' => [
                    'sync',
                    'toggleRotation',
                    'setDefault',
                    'fetchQr',
                    'refreshStatus',
                    'connect',
                ],
            ],
        );
    }

    public function index(Request $request): Response
    {
        $instances = EvolutionInstance::query()
            ->orderByDesc('is_default')
            ->orderByDesc('is_manual')
            ->orderBy('instance_name')
            ->get()
            ->map(fn (EvolutionInstance $i) => $i->toAdminArray());

        $settings = $this->settingsService->toAdminArray();

        return Inertia::render('Admin/WhatsApp/Instances/Index', [
            'instances' => $instances,
            'settings' => $settings,
            'rotationPoolCount' => EvolutionInstance::rotationPoolCount(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'instance_name' => ['required', 'string', 'max:150'],
            'label' => ['nullable', 'string', 'max:150'],
            'set_as_default' => ['nullable', 'boolean'],
            'create_remote' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['instance_name']);

        try {
            if ($request->boolean('create_remote')) {
                $this->evolutionService->clientFor(null, $name)->createInstance([
                    'instanceName' => $name,
                    'integration' => 'WHATSAPP-BAILEYS',
                    'qrcode' => true,
                ]);
            }

            $this->evolutionService->registerManualInstance([
                'instance_name' => $name,
                'label' => $validated['label'] ?? null,
                'verify' => false,
                'set_as_default' => $request->boolean('set_as_default'),
            ]);

            return back()->with('success', 'تمت إضافة الرقم «'.$name.'». امسح QR للربط إن لزم.');
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }

    public function sync(): RedirectResponse
    {
        try {
            $refreshed = $this->evolutionService->syncAllRegisteredInstances();
            $discovered = $this->evolutionService->syncInstances(false);
            $poolCount = EvolutionInstance::rotationPoolCount();

            $message = 'تمت مزامنة '.count($refreshed).' رقم';
            if (count($discovered) > 0) {
                $message .= ' واكتشاف '.count($discovered).' من Evolution';
            }
            $message .= '. أرقام التبديل النشطة: '.$poolCount.'.';

            return back()->with($poolCount >= 1 ? 'success' : 'warning', $message);
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }

    public function toggleRotation(string $instanceName): RedirectResponse
    {
        $instance = EvolutionInstance::where('instance_name', $instanceName)->firstOrFail();
        $instance->update(['rotation_enabled' => ! $instance->rotation_enabled]);
        $status = $instance->fresh()->rotation_enabled ? 'مفعّل' : 'معطّل';

        return back()->with('success', 'تم '.$status.' مشاركة «'.$instance->instance_name.'» في التبديل.');
    }

    public function setDefault(string $instanceName): RedirectResponse
    {
        try {
            $this->evolutionService->assignDefaultInstance($instanceName);

            return back()->with('success', 'تم تعيين «'.$instanceName.'» كافتراضي.');
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }

    public function fetchQr(string $instanceName): RedirectResponse
    {
        try {
            $response = $this->evolutionService->clientFor(null, $instanceName)->connectInstance($instanceName);
            $qr = $response['base64'] ?? $response['qrcode']['base64'] ?? $response['code'] ?? null;

            if ($qr) {
                EvolutionInstance::where('instance_name', $instanceName)->update([
                    'qr_code' => $qr,
                    'connection_status' => 'connecting',
                ]);
            }

            return back()->with('success', $qr ? 'تم جلب رمز QR. افتح صفحة الربط أو أعد تحميل القائمة.' : 'لم يُرجع Evolution رمز QR.');
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }

    public function refreshStatus(string $instanceName): RedirectResponse
    {
        try {
            $instance = EvolutionInstance::where('instance_name', $instanceName)->firstOrFail();
            $this->evolutionService->refreshInstanceFromApi($instance);

            return back()->with('success', 'تم تحديث حالة «'.$instanceName.'».');
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }

    public function destroy(string $instanceName): RedirectResponse
    {
        $instance = EvolutionInstance::where('instance_name', $instanceName)->first();

        if ($instance && ! $instance->is_manual) {
            try {
                $this->evolutionService->clientFor($instance, $instanceName)->deleteInstance($instanceName);
            } catch (Throwable) {
                // still remove locally
            }
        }

        EvolutionInstance::where('instance_name', $instanceName)->delete();

        return back()->with('success', 'تم حذف الرقم من المنصة.');
    }

    public function connect(Request $request, string $instanceName): Response
    {
        $instance = EvolutionInstance::where('instance_name', $instanceName)->firstOrFail();

        return Inertia::render('Admin/WhatsApp/Instances/Connect', [
            'instance' => [
                ...$instance->toAdminArray(),
                'qr_code' => $instance->qr_code,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
