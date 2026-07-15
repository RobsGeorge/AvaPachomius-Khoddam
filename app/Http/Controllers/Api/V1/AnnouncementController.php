<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
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
            'data' => $inbox->map(fn ($delivery) => $this->serializeDelivery($delivery))->values(),
            'meta' => [
                'unread_count' => $this->announcements->unreadCount($user),
            ],
        ]);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($announcement->isPublished(), 404);

        $delivery = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        $this->announcements->markOpened($announcement, $user);
        $this->announcements->markRead($delivery->fresh());

        return response()->json([
            'data' => $this->serializeDelivery($delivery->fresh()->load('announcement')),
        ]);
    }

    public function dismissBanner(Request $request, Announcement $announcement): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($announcement->isPublished(), 404);
        abort_unless($announcement->hasChannel(Announcement::CHANNEL_BANNER_DISMISSIBLE), 403);

        AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->firstOrFail();

        $this->announcements->dismissBanner($announcement, $user);

        return response()->json(['message' => 'ok']);
    }

    /** @return array<string, mixed> */
    private function serializeDelivery(AnnouncementDelivery $delivery): array
    {
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
    }
}
