<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\NotificationFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationFeedService $feed
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = $request->query('filter', 'all');

        return view('notifications.index', [
            'notifications' => $this->feed->inbox($user, $filter === 'all' ? null : $filter),
            'filter' => $filter,
            'filters' => $this->feed->availableFilters(),
            'unreadCount' => $this->feed->unreadCount($user),
        ]);
    }

    public function show(UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->user_id === $user->user_id, 403);

        $this->feed->markRead($notification);

        if ($this->isSafeInternalUrl($notification->action_url)) {
            return redirect($notification->action_url);
        }

        return redirect()->route('notifications.index');
    }

    /**
     * Follow an action_url only when it targets this application (F9 — prevent open
     * redirects). Accepts app-relative paths (but not protocol-relative "//host") and
     * absolute URLs whose host matches the app host; rejects any cross-host target.
     */
    private function isSafeInternalUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        // Protocol-relative ("//evil.example") is an external target in disguise.
        if (Str::startsWith($url, '//')) {
            return false;
        }

        // App-relative path.
        if (Str::startsWith($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host === request()->getHost() || ($appHost !== null && $host === $appHost);
    }

    public function markAllRead()
    {
        $this->feed->markAllRead(Auth::user());

        return back()->with('success', __('notifications.all_marked_read'));
    }

    public function toggleRead(UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->user_id === $user->user_id, 403);

        if ($notification->isUnread()) {
            $this->feed->markRead($notification);

            return back()->with('success', __('notifications.marked_read'));
        }

        $this->feed->markUnread($notification);

        return back()->with('success', __('notifications.marked_unread'));
    }
}
