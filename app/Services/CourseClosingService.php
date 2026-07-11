<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseApplicationForm;
use App\Models\CourseGraduation;
use App\Models\CourseGraduationStudent;
use App\Models\Exam;
use App\Models\GradeItem;
use App\Models\Role;
use App\Models\Session;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseClosingService
{
    public function __construct(
        private GraduationService $graduation,
        private CourseEnrollmentService $enrollment,
        private CourseGraduationMailService $mail,
        private CertificateService $certificates,
        private NotificationGeneratorService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function checklist(Course $course): array
    {
        $course->loadMissing(['gradeCategories.items.grades', 'sessions']);
        $students = $this->enrollment->enrolledStudents($course->course_id);
        $criteriaConfigured = $course->hasGraduationCriteria();
        $ungradedItems = $this->ungradedItemsCount($course, $students);
        $openAttendanceSessions = $course->sessions
            ->filter(fn (Session $s) => $s->attendance_closed_at === null)
            ->count();

        $evaluations = $criteriaConfigured
            ? $this->preview($course)
            : collect();

        return [
            'status'                   => $course->status,
            'criteria_configured'      => $criteriaConfigured,
            'student_count'            => $students->count(),
            'ungraded_items'           => $ungradedItems,
            'open_attendance_sessions' => $openAttendanceSessions,
            'eligible_count'           => $evaluations->where('graduated', true)->count(),
            'can_lock'                 => $course->isActive() && $criteriaConfigured,
            'can_announce'             => $course->status === Course::STATUS_GRADING_LOCKED && $criteriaConfigured,
            'can_close'                => $course->status === Course::STATUS_ANNOUNCED,
        ];
    }

    public function lockGrading(Course $course, User $actor): Course
    {
        if (! $course->isActive()) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.cannot_lock')],
            ]);
        }

        if (! $course->hasGraduationCriteria()) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.criteria_required')],
            ]);
        }

        $course->update([
            'status'            => Course::STATUS_GRADING_LOCKED,
            'grading_locked_at' => now(),
        ]);

        return $course->fresh();
    }

    public function updateGraceConfig(Course $course, array $data): Course
    {
        if ($course->status !== Course::STATUS_GRADING_LOCKED) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.grace_only_when_locked')],
            ]);
        }

        $course->update([
            'grace_marks_enabled'    => (bool) ($data['grace_marks_enabled'] ?? false),
            'max_grace_marks'        => (float) ($data['max_grace_marks'] ?? 0),
            'grace_eligibility_mode' => $data['grace_eligibility_mode'] ?? Course::GRACE_MODE_MANUAL,
        ]);

        return $course->fresh();
    }

    /** @param array<int, array{eligible_for_grace?: bool, pending_grace_marks?: float|null}> $rows */
    public function updateGraceMarks(Course $course, array $rows): void
    {
        if ($course->status !== Course::STATUS_GRADING_LOCKED) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.grace_only_when_locked')],
            ]);
        }

        $studentRoleId = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->value('role_id');

        foreach ($rows as $userId => $row) {
            $enrollment = UserCourseRole::where('course_id', $course->course_id)
                ->where('user_id', $userId)
                ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
                ->first();

            if (! $enrollment) {
                continue;
            }

            $eligible = (bool) ($row['eligible_for_grace'] ?? false);
            $grace = $eligible ? $this->graduation->clampGrace($course, (float) ($row['pending_grace_marks'] ?? 0)) : 0.0;

            $enrollment->update([
                'eligible_for_grace'  => $eligible,
                'pending_grace_marks' => $grace,
            ]);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    public function preview(Course $course): Collection
    {
        $course->loadMissing(['gradeCategories.items.grades']);
        $students = $this->enrollment->enrolledStudents($course->course_id);
        $graceMap = $this->graceMapForCourse($course);

        return $this->graduation->evaluateWithGrace($course, $students, $graceMap);
    }

    public function announce(Course $course, User $actor): CourseGraduation
    {
        if ($course->status !== Course::STATUS_GRADING_LOCKED) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.cannot_announce')],
            ]);
        }

        if (! $course->hasGraduationCriteria()) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.criteria_required')],
            ]);
        }

        return DB::transaction(function () use ($course, $actor) {
            $course->loadMissing(['gradeCategories.items.grades']);
            $students = $this->enrollment->enrolledStudents($course->course_id);
            $graceMap = $this->graceMapForCourse($course);
            $evaluations = $this->graduation->evaluateWithGrace($course, $students, $graceMap);

            $graduation = CourseGraduation::create([
                'course_id'                 => $course->course_id,
                'announced_by_user_id'      => $actor->user_id,
                'announced_at'              => now(),
                'status'                    => CourseGraduation::STATUS_FINAL,
                'passing_percentage'        => $course->passing_percentage,
                'min_attendance_percentage' => $course->min_attendance_percentage,
                'max_grace_marks'           => $course->grace_marks_enabled ? $course->max_grace_marks : 0,
            ]);

            foreach ($evaluations as $row) {
                /** @var User $student */
                $student = $row['user'];

                CourseGraduationStudent::create([
                    'course_graduation_id' => $graduation->id,
                    'user_id'              => $student->user_id,
                    'raw_total_grade'      => $row['raw_total_grade'],
                    'grace_marks_applied'  => $row['grace_marks_applied'],
                    'final_total_grade'    => $row['total_grade'],
                    'attendance_pct'       => $row['attendance_pct'],
                    'letter_grade'         => $row['letter'],
                    'eligible'             => $row['eligible'],
                    'graduated'            => $row['graduated'],
                    'failure_reason'       => $row['failure_reason'],
                    'grades_detail_json'   => $this->graduation->buildGradesDetailJson($course, $student->user_id),
                ]);
            }

            $course->update([
                'status'              => Course::STATUS_ANNOUNCED,
                'grades_announced_at' => now(),
            ]);

            $this->notifyStaffOfAnnouncement($course, $graduation);
            $this->mail->sendGraduationAnnouncements($course, $graduation);

            return $graduation->load('students.user');
        });
    }

    public function close(Course $course, User $actor, bool $archiveStaff = true): Course
    {
        if ($course->status !== Course::STATUS_ANNOUNCED) {
            throw ValidationException::withMessages([
                'course' => [__('course_graduation.errors.cannot_close')],
            ]);
        }

        return DB::transaction(function () use ($course, $actor, $archiveStaff) {
            Exam::where('course_id', $course->course_id)->update(['is_published' => false]);

            CourseApplicationForm::where('course_id', $course->course_id)
                ->update(['is_enabled' => false]);

            if ($archiveStaff) {
                $staffRoleIds = Role::query()
                    ->whereRaw('LOWER(role_name) IN (?, ?)', ['admin', 'instructor'])
                    ->pluck('role_id');

                UserCourseRole::where('course_id', $course->course_id)
                    ->whereIn('role_id', $staffRoleIds)
                    ->where('user_id', '!=', $actor->user_id)
                    ->whereNull('staff_archived_at')
                    ->update(['staff_archived_at' => now()]);
            }

            $graduation = $course->latestGraduation()->with('students')->first();
            if ($graduation) {
                $this->certificates->issueForGraduation($course, $graduation);
            }

            $course->update([
                'status'             => Course::STATUS_CLOSED,
                'closed_at'          => now(),
                'closed_by_user_id'  => $actor->user_id,
            ]);

            return $course->fresh();
        });
    }

    /** @return array<int, float> */
    private function graceMapForCourse(Course $course): array
    {
        if (! $course->grace_marks_enabled) {
            return [];
        }

        $studentRoleId = Role::query()->whereRaw('LOWER(role_name) = ?', ['student'])->value('role_id');

        return UserCourseRole::where('course_id', $course->course_id)
            ->when($studentRoleId, fn ($q) => $q->where('role_id', $studentRoleId))
            ->where('eligible_for_grace', true)
            ->get()
            ->mapWithKeys(fn (UserCourseRole $row) => [
                $row->user_id => $this->graduation->clampGrace($course, (float) ($row->pending_grace_marks ?? 0)),
            ])
            ->all();
    }

    private function ungradedItemsCount(Course $course, Collection $students): int
    {
        if ($students->isEmpty()) {
            return 0;
        }

        $studentIds = $students->pluck('user_id');
        $items = GradeItem::whereIn(
            'category_id',
            $course->gradeCategories->pluck('category_id')
        )->with('grades')->get();

        $count = 0;
        foreach ($items as $item) {
            $gradedIds = $item->grades->whereNotNull('score')->pluck('user_id');
            if ($studentIds->diff($gradedIds)->isNotEmpty()) {
                $count++;
            }
        }

        return $count;
    }

    private function notifyStaffOfAnnouncement(Course $course, CourseGraduation $graduation): void
    {
        $staff = $this->enrollment->courseStaff($course->course_id);
        $graduated = $graduation->students()->where('graduated', true)->count();

        foreach ($staff as $member) {
            $this->notifications->createOrUpdate(
                $member,
                'course_graduation_announced',
                __('course_graduation.notification_announced_title', ['course' => $course->title]),
                __('course_graduation.notification_announced_body', [
                    'course' => $course->title,
                    'graduated' => $graduated,
                    'total' => $graduation->students()->count(),
                ]),
                route('graduation.show', $course->course_id),
                CourseGraduation::class,
                $graduation->id,
                metadata: ['course_id' => $course->course_id],
                dedupeKey: "course_graduation_announced:{$graduation->id}:{$member->user_id}",
            );
        }
    }
}
