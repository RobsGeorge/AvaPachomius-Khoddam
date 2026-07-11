<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\NotificationFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($notification->action_url) {
            return redirect($notification->action_url);
        }

        return redirect()->route('notifications.index');
    }

    public function markAllRead()
    {
        $this->feed->markAllRead(Auth::user());

        return back()->with('success', __('notifications.all_marked_read'));
    }
}
