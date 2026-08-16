<?php

namespace App\Modules\WhatsApp\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppMessageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.whatsapp-messages.list'),
        ];
    }

    public function index(Request $request): Response
    {
        $messages = WhatsAppMessage::query()
            ->with('contact:id,wa_id,name')
            ->latest('id')
            ->paginate(25)
            ->through(fn (WhatsAppMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => mb_strimwidth((string) $m->body, 0, 100, '…'),
                'status' => $m->status,
                'contact' => $m->contact?->wa_id,
                'contact_name' => $m->contact?->name,
                'instance' => data_get($m->payload, 'evolution_instance_name'),
                'created_at' => optional($m->created_at)?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/WhatsApp/Messages/Index', [
            'messages' => $messages,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
