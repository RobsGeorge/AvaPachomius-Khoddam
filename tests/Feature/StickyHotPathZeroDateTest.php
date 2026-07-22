<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class StickyHotPathZeroDateTest extends EventModuleTestCase
{
    public function test_dashboard_survives_zero_onboarding_and_staff_archived_dates(): void
    {
        $studentRole = $this->createRole('student');
        $student = $this->createUser([
            'email' => 'sticky-hotpath@example.com',
            'profile_photo' => '',
            'registration_completed' => true,
        ]);
        $course = $this->createCourse(['title' => 'Sticky Hotpath Course']);
        $this->assignCourseRole($student, $course, $studentRole);

        if (Schema::hasColumn('user', 'student_onboarding_completed_at')) {
            DB::table('user')->where('user_id', $student->user_id)->update([
                'student_onboarding_completed_at' => '0000-00-00 00:00:00',
            ]);
        }

        if (Schema::hasColumn('user_course_role', 'staff_archived_at')) {
            DB::table('user_course_role')
                ->where('user_id', $student->user_id)
                ->where('course_id', $course->course_id)
                ->update(['staff_archived_at' => '0000-00-00 00:00:00']);
        }

        $fresh = $student->fresh();

        $this->actingAs($fresh)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_error_responses_are_not_cacheable(): void
    {
        $response = $this->get('/definitely-missing-route-'.uniqid());
        $response->assertNotFound();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
