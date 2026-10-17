<?php

namespace App\Http\Controllers\Frontend\Student;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationCenter;
use App\Services\Seo\SeoPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's notifications on the public site. Every lookup goes through
 * NotificationCenter, which binds a notification to its owner.
 */
class NotificationsController extends Controller
{
    public function __construct(private readonly NotificationCenter $center) {}

    public function index(Request $request): View
    {
        $state = $request->query('state') === 'unread' ? 'unread' : null;

        return view('frontend.student.notifications', [
            'seo' => SeoPage::make('الإشعارات')->noindex(follow: false),
            'state' => $state,
            'notifications' => $this->center->paginate($request->user(), ['state' => $state]),
        ]);
    }

    /**
     * Mark one as read, then follow its link if it has one.
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $this->center->findForUser($request->user(), $id);
        abort_unless($notification, 404);

        $notification->markAsRead();

        return $notification->action_url && $request->boolean('open')
            ? redirect()->to($notification->action_url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->center->markAllRead($request->user());

        return back()->with('student_success', 'تم تعليم كل الإشعارات كمقروءة.');
    }
}
