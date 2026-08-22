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
        if (RolePreviewService::superadminBypassesPermissions($user)) {
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
        return RolePreviewService::superadminBypassesPermissions($user)
            || $user->canInSystem('system.role.manage');
    }

    public function canManageTemplates(User $user): bool
    {
        return RolePreviewService::superadminBypassesPermissions($user);
    }

    public function canManageSystemRoles(User $user): bool
    {
        return RolePreviewService::superadminBypassesPermissions($user);
    }

    public function canManageGroupVisibility(User $user): bool
    {
        return RolePreviewService::superadminBypassesPermissions($user);
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

    public function isSystemWideMode(User $user, ?ChurchService $service = null): bool
    {
        return $service === null
            && RolePreviewService::superadminBypassesPermissions($user);
    }

    /** @return Collection<int, ChurchService> */
    public function manageableServices(User $user): Collection
    {
        if (! ChurchService::tableReady()) {
            return collect();
        }

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return ChurchService::query()->orderBy('title')->get();
        }

        return $this->serviceContext->selectableServices($user)
            ->filter(fn (ChurchService $service) => $this->canManageService($user, $service)
                || $this->canAssignInService($user, $service))
            ->values();
    }

    /**
     * Resolve the workspace service. Nav session wins.
     * A deep-link ?service= id only syncs the session when the user can select that service.
     * Never falls back to another service the user happens to administer.
     */
    public function resolveService(User $user, mixed $serviceId, mixed $courseId = null): ?ChurchService
    {
        $selectable = $this->serviceContext->selectableServices($user);

        if ($serviceId) {
            $match = $selectable->firstWhere('service_id', (int) $serviceId);
            if ($match) {
                $this->serviceContext->setCurrentService($user, $match);

                return $match;
            }
        }

        if ($courseId) {
            $fromCourse = Course::find($courseId);
            $courseServiceId = $fromCourse?->service_id;
            if ($courseServiceId) {
                $match = $selectable->firstWhere('service_id', (int) $courseServiceId);
                if ($match) {
                    $this->serviceContext->setCurrentService($user, $match);

                    return $match;
                }
            }
        }

        $current = $this->serviceContext->currentService($user);
        if ($current && $selectable->contains('service_id', $current->service_id)) {
            return $current;
        }

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return null;
        }

        return $this->serviceContext->autoSelectSingleService($user);
    }

    /** @return Collection<int, Course> */
    public function manageableCourses(User $user, ?ChurchService $withinService = null): Collection
    {
        if (RolePreviewService::superadminBypassesPermissions($user)) {
            $query = Course::query()->orderByDesc('year')->orderBy('title');
            if ($withinService) {
                $query->where('service_id', $withinService->service_id);
            }

            return $query->get();
        }

        $courseIds = $user->userCourseRoles()
            ->activeStaff()
            ->pluck('course_id')
            ->unique();

        return Course::query()
            ->whereIn('course_id', $courseIds)
            ->when($withinService, fn ($q) => $q->where('service_id', $withinService->service_id))
            ->orderByDesc('year')
            ->orderBy('title')
            ->get()
            ->filter(fn (Course $course) => $this->canManageCourse($user, $course)
                || $this->canAssignInCourse($user, $course))
            ->values();
    }

    public function resolveCourse(User $user, mixed $courseId, ?ChurchService $withinService = null): ?Course
    {
        $manageable = $this->manageableCourses($user, $withinService);

        if (! $courseId) {
            $current = current_course();
            if ($current && $manageable->contains('course_id', $current->course_id)) {
                return $current;
            }

            return $manageable->first();
        }

        $course = Course::find($courseId);
        if (! $course) {
            return null;
        }

        if ($withinService && (int) ($course->service_id ?? 0) !== (int) $withinService->service_id) {
            return null;
        }

        if (RolePreviewService::superadminBypassesPermissions($user)) {
            return $course;
        }

        if ($this->canManageCourse($user, $course) || $this->canAssignInCourse($user, $course)) {
            return $course;
        }

        return null;
    }

    /** @return list<string> */
    public function visibleSections(User $user, ?ChurchService $service = null): array
    {
        $sections = [];

        if ($service) {
            if ($this->canManageService($user, $service) || $this->canAssignInService($user, $service)) {
                $sections[] = 'service';
            }

            if ($this->manageableCourses($user, $service)->isNotEmpty()) {
                $sections[] = 'course';
            }

            return $sections;
        }

        if (! RolePreviewService::superadminBypassesPermissions($user)
            && $this->manageableCourses($user)->isNotEmpty()) {
            $sections[] = 'course';
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
        if ($section === 'assignments') {
            $section = $course ? 'course' : ($service ? 'service' : null);
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $service ??= $this->serviceContext->currentService($user);
        }

        $params = array_filter([
            'course' => $course?->course_id,
            'service' => $service?->service_id,
            'section' => $section,
        ]);

        return route('roles.hub', $params);
    }
}
