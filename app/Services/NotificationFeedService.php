<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationFeedService
{
    public function unreadCount(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->user_id)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->count();
    }

    public function inbox(User $user, ?string $filter = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = UserNotification::query()
            ->where('user_id', $user->user_id)
            ->whereNull('dismissed_at')
            ->latest();

        if ($filter && $filter !== 'all') {
            $types = $this->typesForFilter($filter);
            if ($types !== []) {
                $query->whereIn('type', $types);
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function markRead(UserNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->user_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function dismiss(UserNotification $notification): void
    {
        $notification->update([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);
    }

    /** @return list<string> */
    public function typesForFilter(string $filter): array
    {
        return config("notifications.categories.{$filter}", []);
    }

    public function availableFilters(): array
    {
        return ['all', 'announcements', 'academic', 'events', 'social', 'reminders'];
    }
}
