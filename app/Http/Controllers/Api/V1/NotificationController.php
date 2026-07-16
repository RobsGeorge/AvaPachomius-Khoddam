<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationFeedService $feed,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filter = $request->query('filter', 'all');
        $paginator = $this->feed->inbox($user, $filter === 'all' ? null : (string) $filter);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (UserNotification $n) => $this->serialize($n))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $this->feed->unreadCount($user),
                'filters' => $this->feed->availableFilters(),
            ],
        ]);
    }

    public function show(Request $request, UserNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $notification->user_id === (int) $user->user_id, 403);

        $this->feed->markRead($notification);

        return response()->json(['data' => $this->serialize($notification->fresh())]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $notification->user_id === (int) $user->user_id, 403);

        $this->feed->markRead($notification);

        return response()->json([
            'data' => $this->serialize($notification->fresh()),
            'unread_count' => $this->feed->unreadCount($user),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $this->feed->markAllRead($user);

        return response()->json([
            'marked' => $count,
            'unread_count' => $this->feed->unreadCount($user),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'action_url' => $n->action_url,
            'priority' => $n->priority,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
            'is_unread' => $n->isUnread(),
        ];
    }
}
