<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\CommunicationLog;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationLogService
{
    public function enabled(): bool
    {
        return Schema::hasTable('communication_logs');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): ?CommunicationLog
    {
        if (! $this->enabled()) {
            return null;
        }

        $user = $attributes['user'] ?? null;
        unset($attributes['user']);

        if ($user instanceof User) {
            $attributes['user_id'] = $attributes['user_id'] ?? $user->user_id;
            $attributes['recipient_name'] = $attributes['recipient_name'] ?? $user->displayName();
            $attributes['recipient_email'] = $attributes['recipient_email'] ?? $user->email;
            $attributes['recipient_mobile'] = $attributes['recipient_mobile'] ?? $user->mobile_number;
        }

        if (empty($attributes['tracking_token']) && ($attributes['channel'] ?? null) === CommunicationLog::CHANNEL_EMAIL) {
            $attributes['tracking_token'] = $this->newTrackingToken();
        }

        $attributes['status'] = $attributes['status'] ?? CommunicationLog::STATUS_PENDING;
        $attributes['body_preview'] = $this->preview($attributes['body_preview'] ?? null);

        return CommunicationLog::create($attributes);
    }

    public function markSent(
        ?CommunicationLog $log,
        ?string $providerMessageId = null,
        ?Carbon $at = null
    ): ?CommunicationLog {
        if (! $log) {
            return null;
        }

        $log->update([
            'status' => CommunicationLog::STATUS_SENT,
            'sent_at' => $at ?? now(),
            'provider_message_id' => $providerMessageId ?? $log->provider_message_id,
            'failed_at' => null,
            'failure_reason' => null,
        ]);

        return $log->fresh();
    }

    public function markFailed(?CommunicationLog $log, ?string $reason = null): ?CommunicationLog
    {
        if (! $log) {
            return null;
        }

        $log->update([
            'status' => CommunicationLog::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        return $log->fresh();
    }

    public function markSkipped(?CommunicationLog $log, ?string $reason = null): ?CommunicationLog
    {
        if (! $log) {
            return null;
        }

        $log->update([
            'status' => CommunicationLog::STATUS_SKIPPED,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        return $log->fresh();
    }

    public function markOpenedByToken(string $token): ?CommunicationLog
    {
        if (! $this->enabled() || $token === '') {
            return null;
        }

        $log = CommunicationLog::query()->where('tracking_token', $token)->first();

        if (! $log) {
            return null;
        }

        if ($log->opened_at === null) {
            $log->update(['opened_at' => now()]);
        }

        return $log->fresh();
    }

    public function markOpenedForRelated(?string $relatedType, ?int $relatedId, ?int $userId = null): void
    {
        if (! $this->enabled() || ! $relatedType || ! $relatedId) {
            return;
        }

        $query = CommunicationLog::query()
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->whereNull('opened_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $query->update(['opened_at' => now()]);
    }

    public function markReadForRelated(?string $relatedType, ?int $relatedId, ?int $userId = null): void
    {
        if (! $this->enabled() || ! $relatedType || ! $relatedId) {
            return;
        }

        $query = CommunicationLog::query()
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $now = now();
        $query->where(function (Builder $q) {
            $q->whereNull('read_at')->orWhereNull('opened_at');
        })->get()->each(function (CommunicationLog $log) use ($now) {
            $updates = [];
            if ($log->read_at === null) {
                $updates['read_at'] = $now;
            }
            if ($log->opened_at === null) {
                $updates['opened_at'] = $now;
            }
            if ($updates !== []) {
                $log->update($updates);
            }
        });
    }

    public function markAllReadForUser(int $userId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $now = now();
        CommunicationLog::query()
            ->where('user_id', $userId)
            ->whereIn('channel', [CommunicationLog::CHANNEL_PORTAL, CommunicationLog::CHANNEL_ANNOUNCEMENT])
            ->where(function (Builder $q) {
                $q->whereNull('read_at')->orWhereNull('opened_at');
            })
            ->get()
            ->each(function (CommunicationLog $log) use ($now) {
                $updates = [];
                if ($log->read_at === null) {
                    $updates['read_at'] = $now;
                }
                if ($log->opened_at === null) {
                    $updates['opened_at'] = $now;
                }
                if ($updates !== []) {
                    $log->update($updates);
                }
            });
    }

    /**
     * @param  array{
     *     user_id?: int|null,
     *     course_id?: int|null,
     *     service_id?: int|null,
     *     channel?: string|null,
     *     status?: string|null,
     *     opened?: string|null,
     *     month?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     q?: string|null,
     *     course_ids?: list<int>|null,
     *     service_ids?: list<int>|null,
     *     unrestricted?: bool
     * }  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        $query = CommunicationLog::query()
            ->with(['user', 'course', 'service', 'sentBy'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        if (! ($filters['unrestricted'] ?? false)) {
            $courseIds = array_values(array_filter(array_map('intval', (array) ($filters['course_ids'] ?? []))));
            $serviceIds = array_values(array_filter(array_map('intval', (array) ($filters['service_ids'] ?? []))));

            $query->where(function (Builder $q) use ($courseIds, $serviceIds) {
                if ($courseIds !== []) {
                    $q->orWhereIn('course_id', $courseIds);
                }
                if ($serviceIds !== []) {
                    $q->orWhereIn('service_id', $serviceIds);
                }
                if ($courseIds === [] && $serviceIds === []) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', (int) $filters['service_id']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (($filters['opened'] ?? null) === 'yes') {
            $query->where(function (Builder $q) {
                $q->whereNotNull('opened_at')->orWhereNotNull('read_at');
            });
        } elseif (($filters['opened'] ?? null) === 'no') {
            $query->whereNull('opened_at')->whereNull('read_at');
        }

        if (! empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {
            $start = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $query->whereBetween('sent_at', [$start, $end]);
        } else {
            if (! empty($filters['date_from'])) {
                $query->where('sent_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
            }
            if (! empty($filters['date_to'])) {
                $query->where('sent_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
            }
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('recipient_name', 'like', $term)
                    ->orWhere('recipient_email', 'like', $term)
                    ->orWhere('recipient_mobile', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('user', function (Builder $uq) use ($term) {
                        $uq->where('first_name', 'like', $term)
                            ->orWhere('second_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('mobile_number', 'like', $term);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 40): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $filename = 'communications-'.now()->format('Y-m-d-His').'.csv';
        $rows = $this->filteredQuery($filters)->limit(10000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, [
                __('communications.col_sent_at'),
                __('communications.col_person'),
                __('communications.col_channel'),
                __('communications.col_destination'),
                __('communications.col_subject'),
                __('communications.col_status'),
                __('communications.col_opened'),
                __('communications.col_read'),
                __('communications.col_course'),
                __('communications.col_service'),
                __('communications.col_failure'),
            ]);

            foreach ($rows as $log) {
                /** @var CommunicationLog $log */
                fputcsv($out, [
                    $log->sent_at?->format('Y-m-d H:i:s') ?? '',
                    $log->recipient_name ?? $log->user?->displayName() ?? '',
                    __('communications.channels.'.$log->channel),
                    $log->destination() ?? '',
                    $log->subject ?? '',
                    __('communications.statuses.'.$log->status),
                    $log->opened_at?->format('Y-m-d H:i:s') ?? '',
                    $log->read_at?->format('Y-m-d H:i:s') ?? '',
                    $log->course?->localizedTitle() ?? '',
                    $log->service?->localizedTitle() ?? $log->service?->title ?? '',
                    $log->failure_reason ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function newTrackingToken(): string
    {
        return Str::random(48);
    }

    private function preview(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $plain = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return Str::limit($plain, 280);
    }

    /**
     * Courses the viewer may filter/report on for communications.
     *
     * @return Collection<int, Course>
     */
    public function accessibleCourses(User $viewer, CoursePermissionResolver $resolver): Collection
    {
        if ($viewer->is_superadmin || $viewer->canInSystem('communications.report')) {
            return Course::query()->orderBy('title')->get();
        }

        $ids = [];
        foreach ($viewer->userCourseRoles()->activeStaff()->pluck('course_id') as $courseId) {
            $course = Course::find($courseId);
            if ($course && $resolver->canInCourse($viewer, 'communications.report', $course)) {
                $ids[] = (int) $courseId;
            }
        }

        if ($ids === []) {
            return collect();
        }

        return Course::query()->whereIn('course_id', $ids)->orderBy('title')->get();
    }

    /**
     * @return Collection<int, ChurchService>
     */
    public function accessibleServices(User $viewer, CoursePermissionResolver $resolver): Collection
    {
        if (! ChurchService::tableReady()) {
            return collect();
        }

        if ($viewer->is_superadmin || $viewer->canInSystem('communications.report')) {
            return ChurchService::query()->orderBy('title')->get();
        }

        $serviceIds = $this->accessibleCourses($viewer, $resolver)
            ->pluck('service_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($serviceIds === []) {
            return collect();
        }

        return ChurchService::query()->whereIn('service_id', $serviceIds)->orderBy('title')->get();
    }
}
