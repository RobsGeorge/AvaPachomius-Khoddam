<?php

namespace Tests\Feature\Attendance;

use App\Models\Church;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Session;
use App\Support\People\PlacementMode;
use App\Support\Structure\RosterStatus;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Tests\Support\EventModuleTestCase;

/**
 * GET sessions.attendance.search must not fatal when AttendanceCloseService
 * resolves people-table search (searchPeopleForSession).
 */
class SessionAttendanceSearchTest extends EventModuleTestCase
{
    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::main() ?? $this->createChurch(['slug' => 'att-search-main']);
        TenantContext::set($this->church);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_attendance_search_endpoint_returns_results_json(): void
    {
        $admin = $this->createUser(['email' => 'att-search-admin@example.com']);
        $adminRole = $this->createRole('admin');
        $service = $this->createService(['church_id' => $this->church->church_id]);
        $course = $this->createCourse([
            'title' => 'Search Course',
            'service_id' => $service->service_id,
            'church_id' => $this->church->church_id,
        ]);
        $this->assignCourseRole($admin, $course, $adminRole);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Search Session',
            'session_date' => now()->toDateString(),
        ]);

        $person = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'بحث',
            'second_name' => 'طالب',
            'third_name' => 'اختبار',
            'date_of_birth' => '2015-01-01',
            'is_minor' => true,
        ]);

        PersonPlacement::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'person_id' => $person->person_id,
            'service_id' => $service->service_id,
            'course_id' => $course->course_id,
            'roster_status' => RosterStatus::ACTIVE,
            'placement_mode' => PlacementMode::INFO_ONLY,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('sessions.attendance.search', [
                'session' => $session->session_id,
                'q' => 'بحث',
            ]));

        $response->assertOk()
            ->assertJsonStructure(['results']);

        $results = $response->json('results');
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertSame((int) $person->person_id, (int) $results[0]['person_id']);
    }

    public function test_search_people_for_session_returns_empty_collection_for_blank_query(): void
    {
        $session = new Session(['course_id' => null]);
        $service = app(\App\Services\AttendanceCloseService::class);

        $this->assertTrue(method_exists($service, 'searchPeopleForSession'));
        $this->assertInstanceOf(
            Collection::class,
            $service->searchPeopleForSession($session, '   ')
        );
        $this->assertTrue($service->searchPeopleForSession($session, '   ')->isEmpty());
    }
}
