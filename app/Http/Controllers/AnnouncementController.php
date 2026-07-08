<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements
    ) {}

    public function index()
    {
        $user = Auth::user();
        abort_unless($user->isStudent(), 403);

        $deliveries = $this->announcements->studentInbox($user);

        return view('announcements.index', compact('deliveries'));
    }

    public function show(Announcement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->isPublished(), 404);

        $delivery = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        $this->announcements->markOpened($announcement, $user);
        $this->announcements->markRead($delivery->fresh());

        $announcement->load(['course', 'creator']);

        return view('announcements.show', compact('announcement', 'delivery'));
    }

    public function dismissBanner(Request $request, Announcement $announcement)
    {
        $user = Auth::user();

        abort_unless($announcement->isPublished(), 404);
        abort_unless($announcement->hasChannel(Announcement::CHANNEL_BANNER_DISMISSIBLE), 403);

        AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        $this->announcements->dismissBanner($announcement, $user);

        return back();
    }
}
