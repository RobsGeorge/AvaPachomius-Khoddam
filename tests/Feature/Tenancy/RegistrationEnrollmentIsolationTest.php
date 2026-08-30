<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Course;
use App\Services\RegistrationEnrollmentService;
use App\Tenancy\TenantContext;
use Tests\Support\EventModuleTestCase;

class RegistrationEnrollmentIsolationTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_registration_enrollment_does_not_list_other_church_courses(): void
    {
        $churchA = Church::main();
        $churchB = $this->createChurch([
            'slug' => 'stmark-enroll',
            'name' => 'St Mark Enroll',
            'status' => 'active',
        ]);

        TenantContext::set($churchA);
        $serviceA = $this->createService(['title' => 'Church A Service']);
        $courseA = $this->createCourse([
            'title' => 'Church A Course',
            'service_id' => $serviceA->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        TenantContext::set($churchB);
        $serviceB = $this->createService(['title' => 'Church B Service']);
        $courseB = $this->createCourse([
            'title' => 'Church B Course',
            'service_id' => $serviceB->service_id,
            'status' => Course::STATUS_ACTIVE,
        ]);

        $enrollment = app(RegistrationEnrollmentService::class);

        TenantContext::set($churchA);
        $idsA = $enrollment->eligibleCoursesForService((int) $serviceA->service_id)->pluck('course_id');
        $this->assertTrue($idsA->contains($courseA->course_id));
        $this->assertFalse($idsA->contains($courseB->course_id));

        TenantContext::set($churchB);
        $idsB = $enrollment->eligibleCoursesForService((int) $serviceB->service_id)->pluck('course_id');
        $this->assertTrue($idsB->contains($courseB->course_id));
        $this->assertFalse($idsB->contains($courseA->course_id));
    }
}
