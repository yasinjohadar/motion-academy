<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesPanelResource;
use App\Http\Controllers\Controller;
use App\Jobs\BroadcastWhatsAppMessageJob;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Services\WhatsApp\BroadcastWhatsAppMessage;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppCampaignController extends Controller implements HasMiddleware
{
    use AuthorizesPanelResource;

    public function __construct(
        private BroadcastWhatsAppMessage $broadcastService,
        private WhatsAppSettingsService $settingsService,
    ) {}

    public static function middleware(): array
    {
        return self::panelResourceMiddleware(
            'admin',
            'whatsapp-campaigns',
            true,
        );
    }

    public function index(Request $request): Response
    {
        $campaigns = WhatsAppBroadcast::query()
            ->with('creator:id,name')
            ->latest('id')
            ->paginate(15)
            ->through(fn (WhatsAppBroadcast $b) => [
                'id' => $b->id,
                'message_preview' => mb_strimwidth($b->message_template, 0, 80, '…'),
                'total_recipients' => $b->total_recipients,
                'sent_count' => $b->sent_count,
                'failed_count' => $b->failed_count,
                'status' => $b->status,
                'creator' => $b->creator?->name,
                'created_at' => optional($b->created_at)?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Admin/WhatsApp/Campaigns/Index', [
            'campaigns' => $campaigns,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $users = User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
            ]);

        return Inertia::render('Admin/WhatsApp/Campaigns/Create', [
            'users' => $users,
            'delaySettings' => $this->settingsService->getDelaySettings(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'manual_phones' => ['nullable', 'string', 'max:20000'],
        ]);

        $audience = $this->broadcastService->resolveAudience(
            $validated['user_ids'] ?? [],
            $validated['manual_phones'] ?? '',
        );

        if ($audience->isEmpty()) {
            return back()->with('error', 'اختر مستخدمين أو ألصق أرقاماً صالحة.');
        }

        $broadcast = WhatsAppBroadcast::create([
            'message_template' => $validated['message'],
            'send_type' => WhatsAppBroadcast::TYPE_TEXT,
            'campaign_type' => WhatsAppBroadcast::CAMPAIGN_STANDARD,
            'total_recipients' => $audience->count(),
            'status' => WhatsAppBroadcast::STATUS_PENDING,
            'created_by' => $request->user()->id,
            'meta' => [
                'manual_count' => $audience->whereNull('user_id')->count(),
                'user_count' => $audience->whereNotNull('user_id')->count(),
            ],
        ]);

        $cumulative = 0;
        foreach ($audience as $index => $recipient) {
            WhatsAppBroadcastRecipient::create([
                'broadcast_id' => $broadcast->id,
                'user_id' => $recipient['user_id'],
                'phone' => $recipient['phone'],
                'status' => WhatsAppBroadcastRecipient::STATUS_PENDING,
            ]);

            $step = $this->settingsService->calculateDelay();
            $cumulative += $index === 0 ? 0 : $step;

            BroadcastWhatsAppMessageJob::dispatch(
                $broadcast,
                $recipient['phone'],
                $validated['message'],
                $recipient['user_id'],
                $cumulative,
                $index,
            );
        }

        return redirect()
            ->route('admin.whatsapp-campaigns.show', $broadcast)
            ->with('success', 'تم جدولة الحملة لـ '.$audience->count().' مستلم.');
    }

    public function show(Request $request, int $whatsapp_campaign): Response
    {
        $campaign = WhatsAppBroadcast::query()
            ->with(['creator:id,name', 'recipients.user:id,name,email'])
            ->findOrFail($whatsapp_campaign);

        return Inertia::render('Admin/WhatsApp/Campaigns/Show', [
            'campaign' => [
                'id' => $campaign->id,
                'message_template' => $campaign->message_template,
                'status' => $campaign->status,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'creator' => $campaign->creator?->name,
                'created_at' => optional($campaign->created_at)?->format('Y-m-d H:i'),
                'recipients' => $campaign->recipients->map(fn ($r) => [
                    'id' => $r->id,
                    'phone' => $r->phone,
                    'user' => $r->user?->name,
                    'status' => $r->status,
                    'error_message' => $r->error_message,
                    'sent_at' => optional($r->sent_at)?->format('Y-m-d H:i'),
                ]),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
