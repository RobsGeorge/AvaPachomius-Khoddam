<?php

namespace App\Services;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementRevision;
use App\Models\ChurchService;
use App\Models\CommunicationLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnnouncementService
{
    public function __construct(
        private StudentRosterService $rosterService,
        private CommunicationLogService $communicationLogs,
    ) {}

    public function timezone(): string
    {
        return config('attendance.timezone', config('app.timezone'));
    }

    /** @param array<string, mixed> $data */
    public function createDraft(User $author, array $data): Announcement
    {
        $announcement = Announcement::create([
            'created_by_user_id' => $author->user_id,
            'course_id' => $data['course_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'target_mode' => $data['target_mode'],
            'channels' => $this->normalizeChannels($data['channels'] ?? []),
            'status' => Announcement::STATUS_DRAFT,
            'banner_starts_at' => $data['banner_starts_at'] ?? null,
            'banner_ends_at' => $data['banner_ends_at'] ?? null,
        ]);

        $this->syncTargetUsers($announcement, $data['target_user_ids'] ?? []);
        $this->recordRevision($announcement, $author, AnnouncementRevision::ACTION_CREATED);

        return $announcement->fresh(['targetUsers', 'course', 'service']);
    }

    /** @param array<string, mixed> $data */
    public function updateAnnouncement(Announcement $announcement, User $editor, array $data): Announcement
    {
        $announcement->fill([
            'course_id' => $data['course_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'target_mode' => $data['target_mode'],
            'channels' => $this->normalizeChannels($data['channels'] ?? []),
            'banner_starts_at' => $data['banner_starts_at'] ?? null,
            'banner_ends_at' => $data['banner_ends_at'] ?? null,
        ])->save();

        $this->syncTargetUsers($announcement, $data['target_user_ids'] ?? []);
        $this->recordRevision($announcement, $editor, AnnouncementRevision::ACTION_UPDATED);

        return $announcement->fresh(['targetUsers', 'course', 'service']);
    }

    public function publish(Announcement $announcement, User $publisher, bool $republish = false): Announcement
    {
        return DB::transaction(function () use ($announcement, $publisher, $republish) {
            $announcement->update([
                'status' => Announcement::STATUS_PUBLISHED,
                'published_at' => now($this->timezone()),
                'published_by_user_id' => $publisher->user_id,
            ]);

            $recipients = $this->resolveRecipients($announcement);

            foreach ($recipients as $recipient) {
                AnnouncementDelivery::updateOrCreate(
                    [
                        'announcement_id' => $announcement->announcement_id,
                        'user_id' => $recipient->user_id,
                    ],
                    []
                );

                $this->communicationLogs->record([
                    'user' => $recipient,
                    'channel' => CommunicationLog::CHANNEL_ANNOUNCEMENT,
                    'status' => CommunicationLog::STATUS_SENT,
                    'subject' => $announcement->title,
                    'body_preview' => $announcement->body,
                    'course_id' => $announcement->course_id,
                    'service_id' => $announcement->service_id,
                    'sent_by_user_id' => $publisher->user_id,
                    'related_type' => Announcement::class,
                    'related_id' => $announcement->announcement_id,
                    'sent_at' => now($this->timezone()),
                    'metadata' => ['target_mode' => $announcement->target_mode],
                ]);

                app(NotificationScannerService::class)->notifyAnnouncement($recipient, $announcement);
            }

            if ($announcement->hasChannel(Announcement::CHANNEL_EMAIL)) {
                $this->sendEmails($announcement, $recipients);
            }

            $this->recordRevision(
                $announcement,
                $publisher,
                $republish ? AnnouncementRevision::ACTION_REPUBLISHED : AnnouncementRevision::ACTION_PUBLISHED
            );

            return $announcement->fresh(['deliveries.user', 'revisions.editor', 'publisher', 'creator']);
        });
    }

    public function resendEmails(Announcement $announcement, User $actor): int
    {
        $recipients = $this->deliveriesWithUsers($announcement);

        $count = $this->sendEmails($announcement, $recipients);

        $this->recordRevision($announcement, $actor, AnnouncementRevision::ACTION_EMAIL_RESENT);

        return $count;
    }

    public function markWhatsappDispatched(Announcement $announcement, User $actor, array $userIds): void
    {
        AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->whereIn('user_id', $userIds)
            ->update(['whatsapp_sent_at' => now($this->timezone())]);

        $recipients = User::query()->whereIn('user_id', $userIds)->get();
        foreach ($recipients as $recipient) {
            $this->communicationLogs->markSent(
                $this->communicationLogs->record([
                    'user' => $recipient,
                    'channel' => CommunicationLog::CHANNEL_WHATSAPP,
                    'subject' => $announcement->title,
                    'body_preview' => $announcement->body,
                    'course_id' => $announcement->course_id,
                    'service_id' => $announcement->service_id,
                    'sent_by_user_id' => $actor->user_id,
                    'related_type' => Announcement::class,
                    'related_id' => $announcement->announcement_id,
                    'metadata' => ['manual_whatsapp' => true],
                ])
            );
        }

        $this->recordRevision($announcement, $actor, AnnouncementRevision::ACTION_WHATSAPP_DISPATCHED);
    }

    public function markOpened(Announcement $announcement, User $user): void
    {
        $delivery = AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->first();

        if (! $delivery) {
            return;
        }

        $updates = ['opened_at' => now($this->timezone())];

        if (! $delivery->read_at) {
            $updates['read_at'] = now($this->timezone());
        }

        $delivery->update($updates);

        $this->communicationLogs->markOpenedForRelated(
            Announcement::class,
            (int) $announcement->announcement_id,
            (int) $user->user_id
        );
        $this->communicationLogs->markReadForRelated(
            Announcement::class,
            (int) $announcement->announcement_id,
            (int) $user->user_id
        );
    }

    public function markRead(AnnouncementDelivery $delivery): void
    {
        if ($delivery->read_at) {
            return;
        }

        $delivery->update(['read_at' => now($this->timezone())]);

        $this->communicationLogs->markReadForRelated(
            Announcement::class,
            (int) $delivery->announcement_id,
            (int) $delivery->user_id
        );
    }

    public function dismissBanner(Announcement $announcement, User $user): void
    {
        AnnouncementDelivery::query()
            ->where('announcement_id', $announcement->announcement_id)
            ->where('user_id', $user->user_id)
            ->update(['dismissed_at' => now($this->timezone())]);
    }

    public function unreadCount(User $user): int
    {
        return AnnouncementDelivery::query()
            ->where('user_id', $user->user_id)
            ->whereNull('read_at')
            ->whereHas('announcement', fn ($q) => $q->where('status', Announcement::STATUS_PUBLISHED))
            ->count();
    }

    /** @return Collection<int, AnnouncementDelivery> */
    public function studentInbox(User $user): Collection
    {
        return AnnouncementDelivery::query()
            ->with(['announcement.course', 'announcement.creator'])
            ->where('announcement_deliveries.user_id', $user->user_id)
            ->whereHas('announcement', fn ($q) => $q->where('status', Announcement::STATUS_PUBLISHED))
            ->join('announcements', 'announcements.announcement_id', '=', 'announcement_deliveries.announcement_id')
            ->orderByDesc('announcements.published_at')
            ->select('announcement_deliveries.*')
            ->get();
    }

    /** @return Collection<int, Announcement> */
    public function homepageAnnouncements(User $user): Collection
    {
        $now = now($this->timezone());

        return Announcement::query()
            ->where('status', Announcement::STATUS_PUBLISHED)
            ->whereJsonContains('channels->'.Announcement::CHANNEL_HOMEPAGE, true)
            ->whereHas('deliveries', fn ($q) => $q->where('user_id', $user->user_id))
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, array{announcement: Announcement, delivery: AnnouncementDelivery}> */
    public function activeBanners(User $user): Collection
    {
        $now = now($this->timezone());

        return AnnouncementDelivery::query()
            ->with('announcement')
            ->where('user_id', $user->user_id)
            ->whereHas('announcement', function ($q) use ($now) {
                $q->where('status', Announcement::STATUS_PUBLISHED)
                    ->where(function ($inner) {
                        $inner->whereJsonContains('channels->'.Announcement::CHANNEL_BANNER_DISMISSIBLE, true)
                            ->orWhereJsonContains('channels->'.Announcement::CHANNEL_BANNER_LOCKED, true);
                    })
                    ->where(function ($inner) use ($now) {
                        $inner->whereNull('banner_starts_at')->orWhere('banner_starts_at', '<=', $now);
                    })
                    ->where(function ($inner) use ($now) {
                        $inner->whereNull('banner_ends_at')->orWhere('banner_ends_at', '>=', $now);
                    });
            })
            ->get()
            ->filter(function (AnnouncementDelivery $delivery) {
                $announcement = $delivery->announcement;

                if ($announcement->hasChannel(Announcement::CHANNEL_BANNER_LOCKED)) {
                    return true;
                }

                return $announcement->hasChannel(Announcement::CHANNEL_BANNER_DISMISSIBLE)
                    && $delivery->dismissed_at === null;
            })
            ->map(fn (AnnouncementDelivery $delivery) => [
                'announcement' => $delivery->announcement,
                'delivery' => $delivery,
            ])
            ->values();
    }

    /** @return Collection<int, User> */
    public function resolveRecipients(Announcement $announcement): Collection
    {
        if ($announcement->target_mode === Announcement::TARGET_USERS) {
            return $announcement->targetUsers()->orderBy('first_name')->get();
        }

        if ($announcement->target_mode === Announcement::TARGET_SERVICE || $announcement->service_id) {
            $service = $announcement->service_id
                ? ChurchService::find($announcement->service_id)
                : null;

            if (! $service) {
                return collect();
            }

            return $this->rosterService->serviceMembers($service);
        }

        if (! $announcement->course_id) {
            return collect();
        }

        $course = Course::find($announcement->course_id);

        if (! $course) {
            return collect();
        }

        return $this->rosterService->enrolledStudents($course);
    }

    /** @return Collection<int, User> */
    private function deliveriesWithUsers(Announcement $announcement): Collection
    {
        return AnnouncementDelivery::query()
            ->with('user')
            ->where('announcement_id', $announcement->announcement_id)
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }

    /** @param Collection<int, User> $recipients */
    private function sendEmails(Announcement $announcement, Collection $recipients): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                $this->communicationLogs->markSkipped(
                    $this->communicationLogs->record([
                        'user' => $recipient,
                        'channel' => CommunicationLog::CHANNEL_EMAIL,
                        'subject' => $announcement->title,
                        'body_preview' => $announcement->body,
                        'course_id' => $announcement->course_id,
                        'service_id' => $announcement->service_id,
                        'related_type' => Announcement::class,
                        'related_id' => $announcement->announcement_id,
                        'metadata' => ['announcement_email' => true],
                    ]),
                    __('communications.missing_email')
                );

                continue;
            }

            $log = $this->communicationLogs->record([
                'user' => $recipient,
                'channel' => CommunicationLog::CHANNEL_EMAIL,
                'subject' => $announcement->title,
                'body_preview' => $announcement->body,
                'course_id' => $announcement->course_id,
                'service_id' => $announcement->service_id,
                'related_type' => Announcement::class,
                'related_id' => $announcement->announcement_id,
                'metadata' => ['announcement_email' => true],
            ]);

            try {
                Mail::to($recipient->email)->send(
                    (new AnnouncementMail($announcement, $recipient))
                        ->with(['trackingToken' => $log?->tracking_token])
                );
                AnnouncementDelivery::query()
                    ->where('announcement_id', $announcement->announcement_id)
                    ->where('user_id', $recipient->user_id)
                    ->update(['email_sent_at' => now($this->timezone())]);
                $this->communicationLogs->markSent($log);
                $sent++;
            } catch (\Throwable $e) {
                $this->communicationLogs->markFailed($log, $e->getMessage());
            }
        }

        return $sent;
    }

    /** @param list<int|string> $userIds */
    private function syncTargetUsers(Announcement $announcement, array $userIds): void
    {
        if ($announcement->target_mode !== Announcement::TARGET_USERS) {
            $announcement->targetUsers()->sync([]);

            return;
        }

        $announcement->targetUsers()->sync(array_values(array_unique(array_map('intval', $userIds))));
    }

    /** @param array<string, mixed> $channels */
    private function normalizeChannels(array $channels): array
    {
        $normalized = [];

        foreach (Announcement::channelOptions() as $channel) {
            $normalized[$channel] = (bool) ($channels[$channel] ?? false);
        }

        return $normalized;
    }

    private function recordRevision(Announcement $announcement, User $user, string $action): void
    {
        AnnouncementRevision::create([
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $user->user_id,
            'action' => $action,
            'snapshot' => [
                'title' => $announcement->title,
                'body' => $announcement->body,
                'target_mode' => $announcement->target_mode,
                'course_id' => $announcement->course_id,
                'channels' => $announcement->channels,
                'banner_starts_at' => $announcement->banner_starts_at?->toIso8601String(),
                'banner_ends_at' => $announcement->banner_ends_at?->toIso8601String(),
                'status' => $announcement->status,
            ],
            'created_at' => now($this->timezone()),
        ]);
    }
}
