<?php

namespace Tests\Unit;

use App\Models\AnnouncementDelivery;
use App\Models\UserCourseRole;
use Tests\Support\EventModuleTestCase;

class SafelyCastsDatesTest extends EventModuleTestCase
{
    public function test_user_datetime_cast_tolerates_mysql_zero_dates(): void
    {
        $user = $this->createUser(['email' => 'safe-cast-user@example.com']);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'student_onboarding_completed_at' => '0000-00-00 00:00:00',
            'profile_photo_grace_started_at' => '0000-00-00 00:00:00',
        ]));
        $user->syncOriginal();

        $this->assertNull($user->student_onboarding_completed_at);
        $this->assertNull($user->profile_photo_grace_started_at);
        $this->assertFalse($user->hasRealDateAttribute('student_onboarding_completed_at'));
    }

    public function test_user_course_role_zero_staff_archived_is_not_archived(): void
    {
        $role = UserCourseRole::make([
            'user_id' => 1,
            'course_id' => 1,
            'role_id' => 1,
        ]);
        $role->setRawAttributes(array_merge($role->getAttributes(), [
            'staff_archived_at' => '0000-00-00 00:00:00',
        ]));
        $role->syncOriginal();

        $this->assertNull($role->staff_archived_at);
        $this->assertFalse($role->isStaffArchived());
    }

    public function test_announcement_delivery_zero_dismissed_is_not_dismissed(): void
    {
        $delivery = AnnouncementDelivery::make([
            'announcement_id' => 1,
            'user_id' => 1,
        ]);
        $delivery->setRawAttributes(array_merge($delivery->getAttributes(), [
            'dismissed_at' => '0000-00-00 00:00:00',
            'read_at' => '0000-00-00 00:00:00',
        ]));
        $delivery->syncOriginal();

        $this->assertFalse($delivery->isDismissed());
        $this->assertTrue($delivery->isUnread());
    }
}
