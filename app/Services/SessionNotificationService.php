<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Session;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionNotificationService
{
    public function __construct(
        private StudentRosterService $roster,
        private CoursePermissionResolver $permissions,
        private NotificationGeneratorService $generator,
        private NotificationPreferenceService $preferences,
        private AttendanceLatePolicyService $attendancePolicy,
    ) {}

    public function authorizeNotify(User $user, Session $session): void
    {
        if ($user->is_superadmin ?? false) {
            return;
        }

        $course = $session->course ?? Course::find($session->course_id);
        abort_unless(
            $course && $this->permissions->canInCourse($user, 'session.notify', $course),
            403,
            __('pages.not_authorized')
        );
    }

    /** @return Collection<int, User> */
    public function resolveRecipients(Session $session): Collection
    {
        if (! $session->shouldNotifyStudents()) {
            return collect();
        }

        $session->loadMissing(['notificationTargets', 'course']);
        $course = $session->course ?? Course::find($session->course_id);

        if (! $course) {
            return collect();
        }

        $enrolled = $this->roster->enrolledStudents($course)->keyBy('user_id');
        $targets = $session->notificationTargets;

        if ($targets->isEmpty()) {
            return $enrolled->values();
        }

        $targetIds = $targets->pluck('user_id')->all();

        return $enrolled
            ->only($targetIds)
            ->values();
    }

    public function sessionStartAt(Session $session): Carbon
    {
        return $this->attendancePolicy->sessionStartAt($session);
    }

    public function isFutureSession(Session $session, ?Carbon $now = null): bool
    {
        $now ??= now($this->timezone());

        return $this->sessionStartAt($session)->greaterThan($now);
    }

    /**
     * @return array{count: int, recipients: Collection<int, User>}
     */
    public function notifySession(Session $session, User $triggeredBy, string $source = 'manual'): array
    {
        $this->authorizeNotify($triggeredBy, $session);
        $session->loadMissing(['course']);

        if (! $session->shouldNotifyStudents()) {
            return ['count' => 0, 'recipients' => collect()];
        }

        if (! $this->isFutureSession($session)) {
            return ['count' => 0, 'recipients' => collect()];
        }

        $recipients = $this->resolveRecipients($session);
        $count = 0;

        foreach ($recipients as $student) {
            if ($this->notifyStudent($session, $student, $source)) {
                $count++;
            }
        }

        if ($source === 'manual' && $count > 0) {
            SessionAuditService::log('notify_students', 'success', [
                'session_id' => $session->session_id,
                'course_id' => $session->course_id,
                'recipient_count' => $count,
                'source' => $source,
            ], $triggeredBy);
        }

        return ['count' => $count, 'recipients' => $recipients];
    }

    public function notifyStudent(Session $session, User $student, string $source = 'auto', ?int $leadHours = null): bool
    {
        $session->loadMissing(['course']);
        $startAt = $this->sessionStartAt($session);

        $this->preferences->ensureDefaults($student);
        $leadHours ??= (int) $this->preferences->configValue($student, 'session_upcoming', 'lead_hours', 24);

        $dedupeSuffix = $source === 'manual'
            ? 'manual'
            : "auto:{$leadHours}h";

        $this->generator->createOrUpdate(
            $student,
            'session_upcoming',
            __('notifications.generated.session_upcoming_title', ['title' => $session->session_title ?? '']),
            __('notifications.generated.session_upcoming_body', [
                'date' => $startAt->format('d/m/Y'),
                'time' => $startAt->format('H:i'),
                'course' => $session->course?->title ?? '',
            ]),
            route('sessions.index', ['session_id' => $session->session_id]),
            'session',
            $session->session_id,
            UserNotification::PRIORITY_NORMAL,
            ['course_id' => $session->course_id],
            "session_upcoming:{$session->session_id}:user:{$student->user_id}:{$dedupeSuffix}"
        );

        return true;
    }

    public function nextNotifiableSession(Course|string $course): ?Session
    {
        $courseId = $course instanceof Course ? $course->course_id : $course;
        $now = now($this->timezone());

        $sessions = Session::query()
            ->where('course_id', $courseId)
            ->where('notify_students', true)
            ->orderBy('session_date')
            ->orderBy('session_start_time')
            ->get();

        foreach ($sessions as $session) {
            if ($this->isFutureSession($session, $now)) {
                return $session;
            }
        }

        return null;
    }

    /** @param  list<int|string>  $userIds */
    public function syncTargets(Session $session, array $userIds): void
    {
        $course = $session->course ?? Course::find($session->course_id);
        if (! $course) {
            return;
        }

        $enrolledIds = $this->roster->enrolledStudents($course)->pluck('user_id')->all();
        $validIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && in_array($id, $enrolledIds, true))
            ->unique()
            ->values()
            ->all();

        DB::table('session_notification_targets')
            ->where('session_id', $session->session_id)
            ->delete();

        if ($validIds === []) {
            return;
        }

        $now = now();
        $rows = array_map(fn (int $userId) => [
            'session_id' => $session->session_id,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $validIds);

        DB::table('session_notification_targets')->insert($rows);
    }

    public function timezone(): string
    {
        return (string) config('attendance.timezone', config('app.timezone'));
    }
}
