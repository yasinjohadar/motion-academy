<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationLevel;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationCenter;
use App\Support\NotificationChannelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre.
 *
 * Deliberately ungated by permissions: these are the signed-in user's own
 * notifications, the same way admin.profile.* is not listed in config/permissions.php.
 * Every query is scoped through NotificationCenter, which binds to the current user —
 * a notification is never resolved by id alone.
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationCenter $center) {}

    public function index(Request $request): Response
    {
        $filters = [
            'category' => $request->string('category')->toString() ?: null,
            'level' => $request->string('level')->toString() ?: null,
            'state' => $request->string('state')->toString() ?: null,
        ];

        return Inertia::render('Admin/Notifications/Index', [
            'items' => $this->center->paginate($request->user(), $filters),
            'filters' => $filters,
            'categories' => array_map(fn (NotificationCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ], NotificationCategory::cases()),
            'levels' => array_map(fn (NotificationLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
            ], NotificationLevel::cases()),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $this->center->findForUser($request->user(), $notification);

        if (! $record) {
            return back()->with('error', 'الإشعار غير موجود.');
        }

        $record->markAsRead();

        return back(fallback: route('admin.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $count = $this->center->markAllRead($request->user());

        return back(fallback: route('admin.notifications.index'))
            ->with('success', $count > 0
                ? "تم تحديد {$count} إشعاراً كمقروء."
                : 'لا توجد إشعارات غير مقروءة.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $record = $this->center->findForUser($request->user(), $notification);

        if (! $record) {
            return back()->with('error', 'الإشعار غير موجود.');
        }

        $record->delete();

        return back(fallback: route('admin.notifications.index'))
            ->with('success', 'تم حذف الإشعار.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $count = $this->center->deleteAll($request->user());

        return redirect()->route('admin.notifications.index')
            ->with('success', "تم حذف {$count} إشعاراً.");
    }

    public function preferences(Request $request, NotificationChannelResolver $resolver): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Notifications/Preferences', [
            'matrix' => NotificationPreference::matrixForUser($user),
            'channels' => array_map(fn (NotificationChannel $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'icon' => $channel->icon(),
                'optional' => $channel->isOptional(),
            ], NotificationChannel::cases()),
            'availability' => $resolver->availabilityFor($user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.*' => ['boolean'],
        ]);

        NotificationPreference::syncForUser($request->user(), $validated['preferences']);

        return redirect()->route('admin.notifications.preferences')
            ->with('success', 'تم حفظ تفضيلات الإشعارات.');
    }

    /**
     * Registers this browser for Web Push. Called from the preferences screen after
     * the user grants permission — never on page load.
     *
     * Validated by hand rather than with $request->validate(): bootstrap/app.php limits
     * JSON exception rendering to `api/*`, so a failed validate() here would answer this
     * XHR with an HTML redirect that axios reports as success.
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات التسجيل غير صحيحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? null,
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function unsubscribePush(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات الإلغاء غير صحيحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $request->user()->deletePushSubscription($validator->validated()['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }
}
