<?php

namespace App\Http\Controllers;

use App\Support\NavigationHub;
use App\Services\NotificationFeedService;

class HubController extends Controller
{
    public function academic(NotificationFeedService $notificationFeed)
    {
        $user = auth()->user();
        $links = NavigationHub::academicLinks($user);
        $unreadNotificationBadge = $user ? $notificationFeed->unreadBadgeLabel($user) : '';

        return view('hubs.academic', compact('links', 'unreadNotificationBadge'));
    }

    public function service()
    {
        $user = auth()->user();

        if (! NavigationHub::hasService($user)) {
            abort(403);
        }

        $links = NavigationHub::serviceLinks($user);
        $currentService = current_service();

        return view('hubs.service', compact('links', 'currentService'));
    }

    public function system()
    {
        $user = auth()->user();

        if (! NavigationHub::hasSystem($user)) {
            abort(403);
        }

        $links = NavigationHub::systemLinks($user);

        return view('hubs.system', compact('links'));
    }
}
