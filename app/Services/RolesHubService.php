<?php

namespace App\Services;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use Illuminate\Support\Collection;

class RolesHubService
{
    public function __construct(
        private CoursePermissionResolver $resolver,
        private RolePermissionPolicy $policy,
        private ServiceContextService $serviceContext,
    ) {}

    public function canAccess(User $user): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->canInSystem('system.role.manage') || $user->canInSystem('user.assign_role')) {
            return true;
        }

        if ($user->canInSystem('system.user.assign')) {
            return true;
        }

        return $this->manageableCourses($user)->isNotEmpty()
            || $this->manageableServices($user)->isNotEmpty();
    }

    public function canManageEmailTemplates(User $user): bool
    {
        return ($user->is_superadmin ?? false)
            || $user->canInSystem('system.role.manage');
    }

    public function canManageTemplates(User $user): bool
    {
        return (bool) ($user->is_superadmin ?? false);
    }

    public function canManageSystemRoles(User $user): bool
    {
        return (bool) ($user->is_superadmin ?? false);
    }

    public function canManageGroupVisibility(User $user): bool
    {
        return (bool) ($user->is_superadmin ?? false);
    }

    public function canViewAllAssignments(User $user): bool
    {
        return ($user->is_superadmin ?? false)
            || $user->isAdmin()
            || $user->canInSystem('system.role.manage')
            || $user->canInSystem('user.assign_role');
    }

    public function canManageCourse(User $user, Course $course): bool
    {
        return $this->policy->manageCourseRoles($user, $course);
    }

    public function canAssignInCourse(User $user, Course $course): bool
    {
        return $this->policy->assignUsers($user, $course);
    }

    public function canManageService(User $user, ChurchService $service): bool
    {
        return $this->policy->manageServiceRoles($user, $service);
    }

    public function canAssignInService(User $user, ChurchService $service): bool
    {
        return $this->policy->assignServiceUsers($user, $service)
            || $this->policy->addCrossServiceMember($user, $service);
    }

    /** @return Collection<int, ChurchService> */
    public function manageableServices(User $user): Collection
    {
        if (! ChurchService::tableReady()) {
            return collect();
        }

        if ($user->is_superadmin ?? false) {
            return ChurchService::query()->orderBy('title')->get();
        }

        return $this->serviceContext->selectableServices($user)
            ->filter(fn (ChurchService $service) => $this->canManageService($user, $service)
                || $this->canAssignInService($user, $service))
            ->values();
    }

    public function resolveService(User $user, mixed $serviceId): ?ChurchService
    {
        return $this->serviceContext->resolveAccessibleService($user, $serviceId);
    }

    /** @return Collection<int, Course> */
    public function manageableCourses(User $user): Collection
    {
        if ($user->is_superadmin ?? false) {
            return Course::query()->orderByDesc('year')->orderBy('title')->get();
        }

        $courseIds = $user->userCourseRoles()
            ->activeStaff()
            ->pluck('course_id')
            ->unique();

        return Course::query()
            ->whereIn('course_id', $courseIds)
            ->orderByDesc('year')
            ->orderBy('title')
            ->get()
            ->filter(fn (Course $course) => $this->canManageCourse($user, $course)
                || $this->canAssignInCourse($user, $course))
            ->values();
    }

    public function resolveCourse(User $user, mixed $courseId): ?Course
    {
        if (! $courseId) {
            $current = current_course();
            if ($current && $this->manageableCourses($user)->contains('course_id', $current->course_id)) {
                return $current;
            }

            return $this->manageableCourses($user)->first();
        }

        $course = Course::find($courseId);
        if (! $course) {
            return null;
        }

        if ($user->is_superadmin ?? false) {
            return $course;
        }

        if ($this->canManageCourse($user, $course) || $this->canAssignInCourse($user, $course)) {
            return $course;
        }

        return null;
    }

    /** @return list<string> */
    public function visibleSections(User $user): array
    {
        $sections = [];

        if ($this->manageableServices($user)->isNotEmpty()) {
            $sections[] = 'service';
        }

        if ($this->manageableCourses($user)->isNotEmpty()) {
            $sections[] = 'course';
        }

        if ($this->canViewAllAssignments($user)) {
            $sections[] = 'assignments';
        }

        if ($this->canManageEmailTemplates($user)) {
            $sections[] = 'email-templates';
        }

        if ($this->canManageTemplates($user)) {
            $sections[] = 'templates';
        }

        if ($this->canManageSystemRoles($user)) {
            $sections[] = 'system';
        }

        if ($this->canManageGroupVisibility($user)) {
            $sections[] = 'visibility';
        }

        return $sections;
    }

    public function hubUrl(?Course $course = null, ?string $section = null, ?ChurchService $service = null): string
    {
        $params = array_filter([
            'course' => $course?->course_id,
            'service' => $service?->service_id,
            'section' => $section,
        ]);

        return route('roles.hub', $params);
    }
}
