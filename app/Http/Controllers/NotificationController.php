<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The inbox, shared by both sides of the shop.
 *
 * Deliberately sitting outside the 'customer' and 'admin' route groups: the
 * customer's inbox and the owner's are the same table, the same rows and the
 * same rules — only the chrome around them differs, so index() picks the
 * layout and everything else is identical. Every query is scoped through
 * $request->user()->notifications(), so a reader can only ever touch their
 * own rows and a guessed UUID gets a 404.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view($user->canAccessAdminConsole() ? 'admin.notifications.index' : 'notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Opening a notification: mark it read, then hand the reader on to
     * whatever it was about. Posted rather than linked so the state change
     * carries a CSRF token.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Notifier stores relative paths only, so this can never bounce the
        // reader off-site even if a payload is ever built somewhere else.
        return $url && str_starts_with($url, '/')
            ? redirect()->to($url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return back()->with('status', 'Notification removed.');
    }

    /** Clear out what has already been read, leaving anything still unopened. */
    public function clear(Request $request): RedirectResponse
    {
        $request->user()->readNotifications()->delete();

        return back()->with('status', 'Read notifications cleared.');
    }
}
