<?php

namespace App\Services;

use App\Models\CourseApplication;
use App\Models\ExamSchedule;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F-01 — assembles a per-persona "what needs my attention" summary for the
 * dashboard from existing data (no schema changes). Each focus card is gated by
 * the viewer's persona/permissions so nobody sees another persona's queue.
 */
class DashboardService
{
    public function __construct(
        private StudentRosterService $roster,
        private EventEligibilityService $eventEligibility,
    ) {}

    /**
     * Ordered focus cards for the current user. Empty cards are omitted, so the
     * dashboard only renders the panel when there is something to act on.
     *
     * @return list<array{key:string,icon:string,tone:string,count:int,url:?string,items:list<array<string,mixed>>}>
     */
    public function focusCards(User $user): array
    {
        return collect([
            $this->reviewQueueCard($user),
            $this->upcomingExamsCard($user),
            $this->upcomingEventsCard($user),
        ])->filter()->values()->all();
    }

    /** Course applications awaiting review, scoped to courses the user can administer. */
    private function reviewQueueCard(User $user): ?array
    {
        if (! $user->isInstructorOrAdmin()
            && ! \App\Services\RolePreviewService::superadminBypassesPermissions($user)) {
            return null;
        }

        $courseIds = $this->roster->accessibleCourses($user)->pluck('course_id');
        if ($courseIds->isEmpty()) {
            return null;
        }

        $pending = CourseApplication::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', CourseApplication::STATUS_PENDING_REVIEW)
            ->count();

        if ($pending === 0) {
            return null;
        }

        return [
            'key' => 'review_queue',
            'icon' => 'bi-inbox',
            'tone' => 'warning',
            'count' => $pending,
            'url' => route('course-applications.index'),
            'items' => [],
        ];
    }

    /** Published exams scheduled in the student's enrolled courses over the next two weeks. */
    private function upcomingExamsCard(User $user): ?array
    {
        if (! $user->isStudent()) {
            return null;
        }

        $courseIds = $this->roster->studentEnrolledCourses($user)->pluck('course_id');
        if ($courseIds->isEmpty()) {
            return null;
        }

        $schedules = ExamSchedule::query()
            ->whereHas('exam', fn ($q) => $q->whereIn('course_id', $courseIds)->where('is_published', true))
            ->where('is_completed', false)
            ->whereBetween('scheduled_date', [now(), now()->addDays(14)])
            ->with('exam')
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        return [
            'key' => 'upcoming_exams',
            'icon' => 'bi-journal-check',
            'tone' => 'primary',
            'count' => $schedules->count(),
            'url' => null,
            'items' => $schedules->map(fn (ExamSchedule $s) => [
                'label' => $s->exam?->exam_name ?? '',
                'meta' => $s->scheduled_date?->format('d/m/Y H:i'),
                'url' => route('exams.attempt.lobby', $s->schedule_id),
            ])->all(),
        ];
    }

    /** Upcoming events visible to the user (published, eligibility-filtered). */
    private function upcomingEventsCard(User $user): ?array
    {
        $events = $this->eventEligibility->visibleEvents($user);
        if (! $events instanceof Collection) {
            $events = collect($events);
        }
        $events = $events->take(3);

        if ($events->isEmpty()) {
            return null;
        }

        return [
            'key' => 'upcoming_events',
            'icon' => 'bi-calendar-event',
            'tone' => 'success',
            'count' => $events->count(),
            'url' => route('events.index'),
            'items' => $events->map(fn ($event) => [
                'label' => $event->title,
                'meta' => $event->starts_at?->format('d/m/Y H:i'),
                'url' => route('events.show', $event->event_id ?? $event->getKey()),
            ])->all(),
        ];
    }
}
