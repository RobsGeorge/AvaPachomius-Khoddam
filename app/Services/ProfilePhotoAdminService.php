<?php

namespace App\Services;

use App\Mail\ProfilePhotoApprovedMail;
use App\Mail\ProfilePhotoRejectedMail;
use App\Models\PortalSettings;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoAdminService
{
    /** @var Collection<int, User>|null */
    private ?Collection $studentsCache = null;

    public function __construct(
        private ProfilePhotoGateService $gate,
        private StudentRosterService $roster,
        private NotificationGeneratorService $notifications,
    ) {}

    /** @return Collection<int, User> */
    public function studentReport(?string $filter = null): Collection
    {
        $students = $this->enrolledStudents();

        if ($filter) {
            $students = $students
                ->filter(fn (User $user) => $this->gate->complianceStatus($user) === $filter)
                ->values();
        }

        if ($filter === 'pending_review') {
            return $students
                ->sortBy(function (User $user) {
                    $uploaded = $this->gate->safeDate($user, 'profile_photo_uploaded_at');

                    return $uploaded?->getTimestamp() ?? PHP_INT_MAX;
                })
                ->values();
        }

        return $students;
    }

    /**
     * Human-readable how long a pending photo has been waiting.
     */
    public function pendingWaitLabel(User $user): ?string
    {
        if (! $user->isProfilePhotoPending()) {
            return null;
        }

        $uploaded = $this->gate->safeDate($user, 'profile_photo_uploaded_at');
        if (! $uploaded) {
            return __('profile_photos.waiting_unknown');
        }

        $hours = max(0, (int) $uploaded->diffInHours(now($this->gate->timezone())));

        if ($hours < 1) {
            return __('profile_photos.waiting_under_one_hour');
        }

        if ($hours < 24) {
            return __('profile_photos.waiting_hours', ['count' => $hours]);
        }

        $days = (int) floor($hours / 24);

        return __('profile_photos.waiting_days', ['count' => $days]);
    }

    /**
     * Status tallies from a single student load (avoids 7× full-table scans on the report page).
     *
     * @return array{not_started: int, in_grace: int, overdue: int, pending_review: int, approved: int, rejected: int}
     */
    public function statusCounts(): array
    {
        $counts = [
            'not_started' => 0,
            'in_grace' => 0,
            'overdue' => 0,
            'pending_review' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        foreach ($this->enrolledStudents() as $student) {
            $status = $this->gate->complianceStatus($student);
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    /** @return Collection<int, User> */
    private function enrolledStudents(): Collection
    {
        if ($this->studentsCache !== null) {
            return $this->studentsCache;
        }

        $studentRoleIds = Role::studentRoleIds();

        if ($studentRoleIds->isEmpty()) {
            return $this->studentsCache = collect();
        }

        $studentIds = UserCourseRole::query()
            ->whereIn('role_id', $studentRoleIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return $this->studentsCache = collect();
        }

        return $this->studentsCache = User::query()
            ->whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();
    }

    public function updateSettings(int $graceDays, bool $enabled): PortalSettings
    {
        $settings = PortalSettings::current();
        $wasEnabled = (bool) $settings->profile_photo_gate_enabled;

        $payload = [
            'profile_photo_grace_days' => max(1, min(90, $graceDays)),
            'profile_photo_gate_enabled' => $enabled,
        ];

        if ($enabled && ! $wasEnabled) {
            $payload['profile_photo_gate_enabled_at'] = now($this->gate->timezone());
        }

        $settings->update($payload);

        return $settings->fresh();
    }

    public function extendDeadline(User $student, Carbon $deadline, User $admin): User
    {
        abort_unless($this->gate->adminActions($student)['extend_deadline'], 422);

        $previous = $student->profile_photo_deadline_at;
        $normalized = $deadline->timezone($this->gate->timezone());

        $student->forceFill([
            'profile_photo_deadline_at' => $normalized,
        ])->save();

        $student = $student->fresh();

        AuditLogService::recordEvent('profile_photo.deadline_extended', [
            'actor_user_id' => $admin->user_id,
            'target_user_id' => $student->user_id,
            'previous_deadline_at' => $previous?->toIso8601String(),
            'new_deadline_at' => $normalized->toIso8601String(),
            'status' => $this->gate->complianceStatus($student),
        ]);

        return $student;
    }

    public function resetGraceStart(User $student, User $admin): User
    {
        abort_unless($this->gate->adminActions($student)['reset_grace'], 422);

        $beforeStatus = $student->profile_photo_status;
        $hadPhoto = filled($student->profile_photo);

        if ($student->profile_photo && Storage::disk('public')->exists($student->profile_photo)) {
            Storage::disk('public')->delete($student->profile_photo);
        }

        $student->forceFill([
            'profile_photo' => '',
            'profile_photo_grace_started_at' => null,
            'profile_photo_deadline_at' => null,
            'profile_photo_uploaded_at' => null,
            'profile_photo_status' => null,
            'profile_photo_reviewed_at' => null,
            'profile_photo_reviewed_by_user_id' => null,
            'profile_photo_rejection_note' => null,
        ])->save();

        $student = $student->fresh();

        AuditLogService::recordEvent('profile_photo.grace_reset', [
            'actor_user_id' => $admin->user_id,
            'target_user_id' => $student->user_id,
            'previous_status' => $beforeStatus,
            'had_photo' => $hadPhoto,
        ]);

        return $student;
    }

    public function approve(User $student, User $admin): User
    {
        abort_unless($student->hasProfilePhoto(), 422);
        abort_unless($this->gate->adminActions($student)['approve_reject'], 422);

        $previousStatus = $student->profile_photo_status;

        $student->forceFill([
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
            'profile_photo_reviewed_at' => now($this->gate->timezone()),
            'profile_photo_reviewed_by_user_id' => $admin->user_id,
            'profile_photo_rejection_note' => null,
        ])->save();

        $student = $student->fresh();

        AuditLogService::recordEvent('profile_photo.approved', [
            'actor_user_id' => $admin->user_id,
            'target_user_id' => $student->user_id,
            'previous_status' => $previousStatus,
            'new_status' => User::PHOTO_STATUS_APPROVED,
        ]);

        if (filled($student->email)) {
            try {
                Mail::to($student->email)->send(new ProfilePhotoApprovedMail($student));
            } catch (\Throwable $e) {
                Log::warning('Profile photo approval email failed', [
                    'user_id' => $student->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $student;
    }

    /**
     * @param  list<int|string>  $userIds
     * @return array{approved: int, skipped: int}
     */
    public function approveMany(array $userIds, User $admin): array
    {
        $approved = 0;
        $skipped = 0;

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId < 1) {
                continue;
            }

            $student = User::query()->where('user_id', $userId)->first();
            if (! $student || ! $this->gate->adminActions($student)['approve_reject']) {
                $skipped++;

                continue;
            }

            try {
                $this->approve($student, $admin);
                $approved++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        if ($approved > 0) {
            AuditLogService::recordEvent('profile_photo.bulk_approved', [
                'actor_user_id' => $admin->user_id,
                'approved_count' => $approved,
                'skipped_count' => $skipped,
                'requested_ids' => array_values(array_unique(array_map('intval', $userIds))),
            ]);
        }

        return compact('approved', 'skipped');
    }

    /**
     * @param  list<int|string>  $userIds
     * @return array{rejected: int, skipped: int}
     */
    public function rejectMany(array $userIds, User $admin, ?string $note = null): array
    {
        $rejected = 0;
        $skipped = 0;

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId < 1) {
                continue;
            }

            $student = User::query()->where('user_id', $userId)->first();
            if (! $student || ! $this->gate->adminActions($student)['approve_reject']) {
                $skipped++;

                continue;
            }

            try {
                $this->reject($student, $admin, $note);
                $rejected++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        if ($rejected > 0) {
            AuditLogService::recordEvent('profile_photo.bulk_rejected', [
                'actor_user_id' => $admin->user_id,
                'rejected_count' => $rejected,
                'skipped_count' => $skipped,
                'has_note' => filled($note),
                'requested_ids' => array_values(array_unique(array_map('intval', $userIds))),
            ]);
        }

        return compact('rejected', 'skipped');
    }

    /**
     * Notify course admins (portal + email) that a student photo awaits urgent review.
     */
    public function notifyAdminsOfPendingPhoto(User $student): void
    {
        if (! $student->isProfilePhotoPending()) {
            return;
        }

        $courses = $this->roster->studentEnrolledCourses($student);
        if ($courses->isEmpty()) {
            return;
        }

        $recipients = collect();
        foreach ($courses as $course) {
            $courseId = (string) $course->course_id;
            foreach ($this->roster->courseStaff($courseId) as $member) {
                if ((int) $member->user_id === (int) $student->user_id) {
                    continue;
                }
                if (! $member->isAdmin($courseId)) {
                    continue;
                }
                $recipients->put($member->user_id, $member);
            }
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $name = $student->displayName();
        $uploadedAt = $student->profile_photo_uploaded_at;
        $dedupeSuffix = $uploadedAt instanceof Carbon
            ? (string) $uploadedAt->getTimestamp()
            : (string) now($this->gate->timezone())->getTimestamp();
        $actionUrl = route('admin.profile-photos.index', ['filter' => 'pending_review']);

        foreach ($recipients as $admin) {
            try {
                $this->notifications->createOrUpdate(
                    $admin,
                    'profile_photo_pending_review',
                    __('profile_photos.notification_pending_title'),
                    __('profile_photos.notification_pending_body', ['name' => $name]),
                    $actionUrl,
                    User::class,
                    (int) $student->user_id,
                    UserNotification::PRIORITY_HIGH,
                    [
                        'student_user_id' => $student->user_id,
                        'dedupe_suffix' => $dedupeSuffix,
                    ],
                    "profile_photo_pending_review:{$student->user_id}:{$dedupeSuffix}",
                );
            } catch (\Throwable $e) {
                Log::warning('Profile photo pending-review notification failed', [
                    'student_user_id' => $student->user_id,
                    'admin_user_id' => $admin->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function reject(User $student, User $admin, ?string $note = null): User
    {
        abort_unless($this->gate->adminActions($student)['approve_reject'], 422);

        $previousStatus = $student->profile_photo_status;

        if ($student->profile_photo && Storage::disk('public')->exists($student->profile_photo)) {
            Storage::disk('public')->delete($student->profile_photo);
        }

        $student->forceFill([
            'profile_photo' => '',
            'profile_photo_status' => User::PHOTO_STATUS_REJECTED,
            'profile_photo_uploaded_at' => null,
            'profile_photo_grace_started_at' => null,
            'profile_photo_deadline_at' => null,
            'profile_photo_reviewed_at' => now($this->gate->timezone()),
            'profile_photo_reviewed_by_user_id' => $admin->user_id,
            'profile_photo_rejection_note' => $note,
        ])->save();

        $student = $student->fresh();

        AuditLogService::recordEvent('profile_photo.rejected', [
            'actor_user_id' => $admin->user_id,
            'target_user_id' => $student->user_id,
            'previous_status' => $previousStatus,
            'new_status' => User::PHOTO_STATUS_REJECTED,
            'has_note' => filled($note),
        ]);

        if (filled($student->email)) {
            try {
                Mail::to($student->email)->send(new ProfilePhotoRejectedMail($student));
            } catch (\Throwable $e) {
                Log::warning('Profile photo rejection email failed', [
                    'user_id' => $student->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $student;
    }
}
