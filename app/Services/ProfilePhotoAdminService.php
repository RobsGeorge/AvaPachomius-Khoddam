<?php

namespace App\Services;

use App\Models\PortalSettings;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoAdminService
{
    public function __construct(
        private ProfilePhotoGateService $gate
    ) {}

    /** @return Collection<int, User> */
    public function studentReport(?string $filter = null): Collection
    {
        $studentRoleId = Role::query()
            ->whereRaw('LOWER(role_name) = ?', ['student'])
            ->value('role_id');

        if (! $studentRoleId) {
            return collect();
        }

        $studentIds = UserCourseRole::query()
            ->where('role_id', $studentRoleId)
            ->pluck('user_id')
            ->unique();

        $students = User::query()
            ->whereIn('user_id', $studentIds)
            ->orderBy('first_name')
            ->orderBy('second_name')
            ->get();

        if ($filter) {
            $students = $students->filter(fn (User $user) => $this->gate->reportStatus($user) === $filter)->values();
        }

        return $students;
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
            'profile_photo_reviewed_at' => now($this->gate->timezone()),
            'profile_photo_reviewed_by_user_id' => $admin->user_id,
            'profile_photo_rejection_note' => $note,
        ])->save();

        return $student->fresh();
    }
}
