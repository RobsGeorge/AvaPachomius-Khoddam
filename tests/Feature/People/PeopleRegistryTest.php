<?php

namespace Tests\Feature\People;

use App\Models\Attendance;
use App\Models\Church;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Person;
use App\Models\Session;
use App\Models\User;
use App\Services\People\PersonDuplicateDetector;
use App\Services\People\PersonMergeService;
use App\Services\People\PersonRegistryService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

class PeopleRegistryTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_schema_has_people_families_and_user_person_id(): void
    {
        $this->assertTrue(Schema::hasTable('people'));
        $this->assertTrue(Schema::hasTable('families'));
        $this->assertTrue(Schema::hasTable('family_members'));
        $this->assertTrue(Schema::hasTable('relationships'));
        $this->assertTrue(Schema::hasColumn('user', 'person_id'));
        $this->assertTrue(Schema::hasColumn('people', 'normalized_name'));
    }

    public function test_creating_user_auto_links_person(): void
    {
        $user = $this->createUser([
            'first_name' => 'هالة',
            'second_name' => 'محمد',
            'third_name' => 'علي',
            'email' => 'hala-auto@example.com',
        ]);

        $user->refresh();
        $this->assertNotNull($user->person_id);

        $person = Person::withoutTenancy()->find($user->person_id);
        $this->assertNotNull($person);
        $this->assertSame('هاله محمد علي', $person->normalized_name);
    }

    public function test_backfill_reconciles_and_writes_report(): void
    {
        // Simulate legacy users without person_id (observer would have set it — clear after).
        $u1 = $this->createUser(['email' => 'bf1@example.com']);
        $u2 = $this->createUser(['email' => 'bf2@example.com']);
        $personIds = array_filter([$u1->fresh()->person_id, $u2->fresh()->person_id]);
        User::query()->whereIn('user_id', [$u1->user_id, $u2->user_id])->update(['person_id' => null]);
        Person::withoutTenancy()->whereIn('person_id', $personIds)->delete();

        $report = 'docs/migrations/p2.1-report.md';
        $exit = Artisan::call('people:backfill', ['--report' => $report]);
        $this->assertSame(0, $exit);

        $this->assertSame(
            User::query()->count(),
            User::query()->whereNotNull('person_id')->count()
        );
        $this->assertTrue(File::exists(base_path($report)));
        $this->assertStringContainsString('Every user has person_id', File::get(base_path($report)));
        $this->assertStringContainsString('| yes |', File::get(base_path($report)));
    }

    public function test_hala_import_csv_flags_intra_batch_duplicates(): void
    {
        $path = storage_path('app/p2_1_hala_import.csv');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "first_name,second_name,third_name,date_of_birth,mobile_number\n".
            "هالة,محمد,علي,1990-01-01,1011111111\n".
            "هاله,محمد,علي,1990-01-01,1022222222\n");

        $this->artisan('people:check-duplicates', ['file' => $path])
            ->expectsOutputToContain('Intra-file collisions')
            ->expectsOutputToContain('هاله محمد علي')
            ->assertSuccessful();
    }

    public function test_duplicate_detector_matches_normalized_name(): void
    {
        $churchId = Church::main()->church_id;
        Person::withoutTenancy()->create([
            'church_id' => $churchId,
            'first_name' => 'هالة',
            'second_name' => 'سامي',
            'third_name' => 'نبيل',
            'display_name' => 'هالة سامي نبيل',
            'date_of_birth' => '1991-05-05',
            'mobile_number' => '1012345678',
        ]);

        $matches = app(PersonDuplicateDetector::class)->findPossibleMatches([
            'first_name' => 'هاله',
            'second_name' => 'سامي',
            'third_name' => 'نبيل',
            'church_id' => $churchId,
        ]);

        $this->assertCount(1, $matches);
    }

    public function test_merge_repoints_users_and_keeps_enrollments_attendance(): void
    {
        $admin = $this->createUser(['email' => 'merge-admin@example.com']);
        $role = $this->createRole('Student');
        $course = $this->createCourse(['title' => 'Merge Course']);

        $survivorUser = $this->createUser([
            'first_name' => 'هالة',
            'second_name' => 'كمال',
            'third_name' => 'فؤاد',
            'email' => 'merge-surv@example.com',
        ]);
        $duplicateUser = $this->createUser([
            'first_name' => 'هاله',
            'second_name' => 'كمال',
            'third_name' => 'فؤاد',
            'email' => 'merge-dup@example.com',
        ]);

        $this->assignCourseRole($survivorUser, $course, $role);
        $this->assignCourseRole($duplicateUser, $course, $role);

        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Merge Session',
            'session_date' => now()->toDateString(),
        ]);

        Attendance::create([
            'user_id' => $duplicateUser->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $admin->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
        ]);

        $survivor = Person::withoutTenancy()->findOrFail($survivorUser->person_id);
        $duplicate = Person::withoutTenancy()->findOrFail($duplicateUser->person_id);

        $family = Family::withoutTenancy()->create([
            'church_id' => Church::main()->church_id,
            'name' => 'Test Family',
        ]);
        FamilyMember::create([
            'family_id' => $family->family_id,
            'person_id' => $duplicate->person_id,
            'role' => 'member',
        ]);

        $summary = app(PersonMergeService::class)->merge($survivor, $duplicate, $admin);

        $duplicateUser->refresh();
        $duplicate->refresh();

        $this->assertSame($survivor->person_id, $duplicateUser->person_id);
        $this->assertNotNull($duplicate->retired_at);
        $this->assertSame($survivor->person_id, $duplicate->merged_into_person_id);
        $this->assertSame(1, $summary['attendance_intact']);
        $this->assertGreaterThanOrEqual(1, $summary['enrollments_intact']);
        $this->assertSame(1, Attendance::query()->where('user_id', $duplicateUser->user_id)->count());
        $this->assertTrue(
            FamilyMember::query()
                ->where('family_id', $family->family_id)
                ->where('person_id', $survivor->person_id)
                ->exists()
        );
    }

    public function test_person_is_church_scoped(): void
    {
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'people-b', 'name' => 'People B', 'status' => 'active']);

        TenantContext::set($churchA);
        $personA = app(PersonRegistryService::class)->createPerson([
            'church_id' => $churchA->church_id,
            'first_name' => 'أ',
            'second_name' => 'ب',
            'third_name' => 'ج',
            'display_name' => 'أ ب ج',
        ], true);

        TenantContext::set($churchB);
        $this->assertNull(Person::find($personA->person_id));

        TenantContext::set($churchA);
        $this->assertNotNull(Person::find($personA->person_id));
    }
}
