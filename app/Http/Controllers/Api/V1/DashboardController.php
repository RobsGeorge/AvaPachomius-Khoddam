<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\NotificationFeedService;
use App\Services\StudentRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private NotificationFeedService $notifications,
        private AnnouncementService $announcements,
        private StudentRosterService $roster,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $courses = $this->roster->studentEnrolledCourses($user);

        return response()->json([
            'data' => [
                'display_name' => $user->displayName(),
                'unread_notifications' => $this->notifications->unreadCount($user),
                'unread_announcements' => $this->announcements->unreadCount($user),
                'courses' => $courses->map(fn ($c) => [
                    'course_id' => $c->course_id,
                    'title' => method_exists($c, 'localizedTitle') ? $c->localizedTitle() : $c->title,
                ])->values(),
            ],
        ]);
    }
}
