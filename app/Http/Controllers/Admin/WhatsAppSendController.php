<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvolutionInstance;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\Evolution\EvolutionApiException;
use App\Services\WhatsApp\Evolution\EvolutionRotatingSendService;
use App\Services\WhatsApp\Evolution\EvolutionService;
use App\Services\WhatsApp\SendWhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WhatsAppSendController extends Controller implements HasMiddleware
{
    public function __construct(
        private EvolutionService $evolutionService,
        private EvolutionRotatingSendService $rotatingSendService,
        private SendWhatsAppMessage $sendService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.whatsapp-send.create'),
        ];
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/WhatsApp/Send', [
            'instanceName' => $this->evolutionService->activeInstanceName(),
            'rotationActive' => $this->rotatingSendService->isRotationActive(),
            'rotationPoolCount' => EvolutionInstance::rotationPoolCount(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:100'],
            'text' => ['required', 'string', 'max:5000'],
            'sync' => ['nullable', 'boolean'],
        ]);

        try {
            if ($request->boolean('sync', true)) {
                $message = $this->sendService->sendTextSync($validated['to'], $validated['text']);
                $instance = data_get($message->payload, 'evolution_instance_name', '—');

                if ($message->status !== WhatsAppMessage::STATUS_SENT) {
                    return back()->with('error', data_get($message->error, 'message', 'فشل الإرسال.'));
                }

                return back()->with('success', 'تم الإرسال إلى '.$validated['to'].' عبر «'.$instance.'».');
            }

            $result = $this->rotatingSendService->sendWithRotation(
                fn (string $instanceName) => $this->evolutionService
                    ->providerForInstance($instanceName)
                    ->sendText($validated['to'], $validated['text'])
            );

            return back()->with(
                'success',
                'تم الإرسال إلى '.$validated['to'].' عبر «'.$result['instance_name'].'».'
            );
        } catch (Throwable $e) {
            return back()->with('error', EvolutionApiException::resolveUserMessage($e));
        }
    }
}
