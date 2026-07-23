<?php

namespace App\Services;

use App\Mail\ProfilePhotoRejectedMail;
use App\Models\PortalSettings;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
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
        private ProfilePhotoGateService $gate
    ) {}

    /** @return Collection<int, User> */
    public function studentReport(?string $filter = null): Collection
    {
        $students = $this->enrolledStudents();

        if ($filter) {
            return $students
                ->filter(fn (User $user) => $this->gate->complianceStatus($user) === $filter)
                ->values();
        }

        return $students;
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
        $student->forceFill([
            'profile_photo_deadline_at' => $deadline->timezone($this->gate->timezone()),
        ])->save();

        return $student->fresh();
    }

    public function resetGraceStart(User $student, User $admin): User
    {
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

        return $student->fresh();
    }

    public function approve(User $student, User $admin): User
    {
        abort_unless($student->hasProfilePhoto(), 422);

        $student->forceFill([
            'profile_photo_status' => User::PHOTO_STATUS_APPROVED,
            'profile_photo_reviewed_at' => now($this->gate->timezone()),
            'profile_photo_reviewed_by_user_id' => $admin->user_id,
            'profile_photo_rejection_note' => null,
        ])->save();

        return $student->fresh();
    }

    public function reject(User $student, User $admin, ?string $note = null): User
    {
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
