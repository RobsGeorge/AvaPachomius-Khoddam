<?php

namespace App\Services\Auth;

use App\Models\AccountRecoveryChallenge;
use App\Models\Course;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\Auth\Recovery\AccountRecoveryService;
use App\Services\CoursePermissionResolver;
use App\Services\StudentRosterService;
use Illuminate\Support\Collection;

final class AdminPasswordResetService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
        private StudentRosterService $roster,
        private AccountRecoveryService $recovery,
    ) {}

    public function accessibleCourses(User $actor): Collection
    {
        if ($actor->is_superadmin ?? false) {
            return Course::query()->orderBy('title')->get();
        }

        return $this->roster->accessibleCourses($actor)
            ->filter(fn (Course $course) => $this->resolver->canInCourse($actor, 'roster.password_reset', $course))
            ->values();
    }

    public function canAccessPanel(User $actor): bool
    {
        if ($actor->is_superadmin ?? false) {
            return true;
        }

        return $this->accessibleCourses($actor)->isNotEmpty();
    }

    public function actorCanReset(User $actor, User $subject): bool
    {
        if ($actor->is_superadmin ?? false) {
            return true;
        }

        $studentRoleIds = Role::studentRoleIds();
        if ($studentRoleIds->isEmpty()) {
            return false;
        }

        $courseIds = $this->accessibleCourses($actor)->pluck('course_id');
        if ($courseIds->isEmpty()) {
            return false;
        }

        return UserCourseRole::query()
            ->where('user_id', $subject->user_id)
            ->whereIn('course_id', $courseIds)
            ->whereIn('role_id', $studentRoleIds)
            ->exists();
    }

    public function search(User $actor, string $q, ?string $courseId = null): Collection
    {
        $query = User::query()->orderBy('first_name')->orderBy('second_name');

        if (! ($actor->is_superadmin ?? false)) {
            $courses = $this->accessibleCourses($actor);
            if ($courseId) {
                $courses = $courses->where('course_id', $courseId)->values();
            }
            $studentIds = $this->studentIdsInCourses($courses);
            if ($studentIds->isEmpty()) {
                return collect();
            }
            $query->whereIn('user_id', $studentIds);
        } elseif ($courseId) {
            $course = Course::find($courseId);
            if (! $course) {
                return collect();
            }
            $studentIds = $this->roster->enrolledStudents($course)->pluck('user_id');
            $query->whereIn('user_id', $studentIds);
        }

        $q = trim($q);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($q, $like) {
                $inner->where('email', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like)
                    ->orWhere('national_id', $q)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('second_name', 'like', $like)
                    ->orWhere('third_name', 'like', $like);
                if (ctype_digit($q)) {
                    $inner->orWhere('user_id', (int) $q);
                }
            });
        } elseif ($actor->is_superadmin ?? false) {
            if (! $courseId) {
                return collect();
            }
        }

        return $query->limit(50)->get();
    }

    /**
     * @return array{ok: bool, reason?: string, status?: string, challenge?: AccountRecoveryChallenge}
     */
    public function send(User $actor, User $subject): array
    {
        abort_unless($this->actorCanReset($actor, $subject), 403);

        return $this->recovery->sendAdminPasswordResetLink($actor, $subject);
    }

    private function studentIdsInCourses(Collection $courses): Collection
    {
        $courseIds = $courses->pluck('course_id');
        if ($courseIds->isEmpty()) {
            return collect();
        }

        $studentRoleIds = Role::studentRoleIds();

        return UserCourseRole::query()
            ->whereIn('course_id', $courseIds)
            ->when($studentRoleIds->isNotEmpty(), fn ($q) => $q->whereIn('role_id', $studentRoleIds))
            ->pluck('user_id')
            ->unique()
            ->values();
    }
}
