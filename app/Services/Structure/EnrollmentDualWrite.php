<?php

namespace App\Services\Structure;

use App\Models\Enrollment;
use App\Models\ServiceUnit;
use App\Models\UserCourseRole;
use Illuminate\Support\Facades\Schema;

/**
 * Dual-write user_course_role → enrollments (T8b). UCR remains source of truth.
 */
class EnrollmentDualWrite
{
    public function syncFromUserCourseRole(UserCourseRole $assignment): ?Enrollment
    {
        if (! Schema::hasTable('enrollments') || ! $assignment->user_course_role_id) {
            return null;
        }

        $serviceUnitId = null;
        if (Schema::hasTable('service_units') && $assignment->course_id) {
            $serviceUnitId = ServiceUnit::query()
                ->where('course_id', $assignment->course_id)
                ->value('service_unit_id');
        }

        $status = Enrollment::STATUS_ACTIVE;
        if ($assignment->staff_archived_at) {
            $status = Enrollment::STATUS_ARCHIVED;
        }

        $enrollment = Enrollment::query()->firstOrNew([
            'user_course_role_id' => $assignment->user_course_role_id,
        ]);

        $enrollment->fill([
            'church_id' => $assignment->church_id,
            'user_id' => $assignment->user_id,
            'course_id' => $assignment->course_id,
            'role_id' => $assignment->role_id,
            'service_unit_id' => $serviceUnitId,
            'status' => $status,
        ]);
        $enrollment->save();

        return $enrollment->fresh();
    }

    public function removeForUserCourseRole(UserCourseRole $assignment): void
    {
        if (! Schema::hasTable('enrollments') || ! $assignment->user_course_role_id) {
            return;
        }

        Enrollment::query()
            ->where('user_course_role_id', $assignment->user_course_role_id)
            ->delete();
    }
}
