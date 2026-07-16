<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Course;
use App\Models\User;
use App\Services\CoursePermissionResolver;
use App\Services\StudentRosterService;

trait AuthorizesStudentCourse
{
    protected function authorizeCourseAccess(User $user, Course|int|string $course): Course
    {
        $model = $course instanceof Course
            ? $course
            : Course::query()->findOrFail($course);

        app(StudentRosterService::class)->authorizeCourse($user, (string) $model->course_id);

        return $model;
    }

    protected function authorizeCoursePermission(User $user, Course $course, string $permission): void
    {
        $this->authorizeCourseAccess($user, $course);

        if ($user->is_superadmin ?? false) {
            return;
        }

        abort_unless(
            app(CoursePermissionResolver::class)->canInCourse($user, $permission, $course),
            403
        );
    }
}
