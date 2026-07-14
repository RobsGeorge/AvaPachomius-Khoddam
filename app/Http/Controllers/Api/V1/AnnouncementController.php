<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $inbox = $this->announcements->studentInbox($user);

        return response()->json([
            'data' => $inbox->map(function ($delivery) {
                $announcement = $delivery->announcement;

                return [
                    'delivery_id' => $delivery->delivery_id,
                    'announcement_id' => $announcement?->announcement_id,
                    'title' => $announcement?->title,
                    'body' => $announcement?->body,
                    'published_at' => $announcement?->published_at?->toIso8601String(),
                    'course_id' => $announcement?->course_id,
                    'service_id' => $announcement?->service_id,
                    'read_at' => $delivery->read_at?->toIso8601String(),
                    'opened_at' => $delivery->opened_at?->toIso8601String(),
                    'is_unread' => $delivery->isUnread(),
                ];
            })->values(),
            'meta' => [
                'unread_count' => $this->announcements->unreadCount($user),
            ],
        ]);
    }
}
