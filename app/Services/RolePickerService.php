<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Single source of truth for role dropdowns.
 * Never use Role::orderBy()->get() for pickers — each course has its own role rows.
 */
class RolePickerService
{
    /** Assignable roles for one course (excludes templates and system roles). */
    public function forCourse(int|string $courseId): Collection
    {
        return Role::query()
            ->forCourse($courseId)
            ->orderBy('role_name')
            ->get();
    }

    /** Assignable roles grouped by course_id (multi-course pickers with optgroups / JS filter). */
    public function groupedByCourse(): Collection
    {
        return Role::query()
            ->assignableToCourses()
            ->with('course')
            ->orderBy('role_name')
            ->get()
            ->groupBy('course_id');
    }

    /**
     * Distinct role labels for semantic matching (e.g. event visibility by role name).
     * One entry per slug across all courses.
     *
     * @return Collection<int, string>
     */
    public function distinctEligibleRoleNames(): Collection
    {
        return Role::query()
            ->assignableToCourses()
            ->orderBy('role_name')
            ->get()
            ->unique(fn (Role $role) => $role->effectiveSlug())
            ->pluck('role_name')
            ->values();
    }
}
