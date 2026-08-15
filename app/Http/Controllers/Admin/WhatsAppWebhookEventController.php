<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbound webhook events received from the WhatsApp providers.
 *
 * This screen used to live on ObservabilityController, which meant a core controller
 * imported a WhatsApp model. The screen is not a general observability feature — it
 * reads one module's table and shows one module's payloads — so it belongs on the
 * module's side of the boundary.
 *
 * The route name is unchanged (`admin.observability.webhooks`), so the sidebar, the
 * observability hub and every existing link keep working.
 */
class WhatsAppWebhookEventController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // Same permission as before the move: it is still an operator-facing log.
        return [new Middleware('permission:admin.observability.jobs')];
    }

    public function index(Request $request): Response
    {
        $state = $request->string('state')->toString() ?: null;
        $type = $request->string('event_type')->toString() ?: null;

        $items = WhatsAppWebhookEvent::query()
            ->when($type, fn ($q) => $q->where('event_type', $type))
            ->when($state === 'unprocessed', fn ($q) => $q->whereNull('processed_at'))
            ->when($state === 'processed', fn ($q) => $q->whereNotNull('processed_at'))
            ->latest('id')
            ->paginate((int) config('observability.per_page', 25))
            ->withQueryString()
            ->through(fn (WhatsAppWebhookEvent $event): array => $event->toAdminArray());

        return Inertia::render('Admin/Observability/WebhookEvents', [
            'items' => $items,
            'filters' => ['state' => $state, 'event_type' => $type],
            'types' => WhatsAppWebhookEvent::query()
                ->distinct()->orderBy('event_type')->pluck('event_type')->filter()->values()->all(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
