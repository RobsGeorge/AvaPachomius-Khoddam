<?php

namespace Tests\Feature\Demo;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Priest;
use App\Models\User;
use App\Support\Demo\DemoData;
use App\Tenancy\TenantContext;
use Database\Seeders\DemoDataSeeder;
use Tests\Support\EventModuleTestCase;

/**
 * The demo dataset seeds a rich, logged-in-able graph and wipes cleanly — without ever
 * touching non-demo data.
 */
class DemoDataSeederTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function runSeeder(): DemoDataSeeder
    {
        $seeder = new DemoDataSeeder();
        $seeder->setContainer($this->app);
        $seeder->run();
        TenantContext::clear();

        return $seeder;
    }

    public function test_it_seeds_churches_users_and_scoped_data(): void
    {
        $seeder = $this->runSeeder();

        // Two churches (tenants), each provisioned with capabilities + role templates.
        $stmark = Church::where('slug', 'demo-stmark')->first();
        $stgeorge = Church::where('slug', 'demo-stgeorge')->first();
        $this->assertNotNull($stmark);
        $this->assertNotNull($stgeorge);
        $this->assertTrue($stmark->hasCapability('exams'));
        $this->assertTrue($stmark->hasCapability('church_management'));

        // The admin is a member of St Mark and can log in (verified + approved + password).
        $admin = User::where('email', DemoData::email('admin.stmark'))->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->is_verified && $admin->registration_completed);
        $this->assertTrue(
            ChurchUser::where('church_id', $stmark->church_id)->where('user_id', $admin->user_id)->exists()
        );

        // Priests, a course, and course-role assignments exist under the demo churches.
        TenantContext::set($stmark);
        $this->assertGreaterThanOrEqual(2, Priest::count());
        $course = Course::where('title', 'Foundations of Faith')->first();
        $this->assertNotNull($course);
        $this->assertGreaterThanOrEqual(5, $course->userCourseRoles()->count());
        TenantContext::clear();

        // Credentials were captured with the shared password.
        $this->assertNotEmpty($seeder->credentials);
        $this->assertSame(DemoData::password(), $seeder->credentials[0]['password']);
        $this->assertGreaterThan(10, count($seeder->credentials));
    }

    public function test_it_is_idempotent_and_skips_when_already_seeded(): void
    {
        $this->runSeeder();
        $churchesAfterFirst = Church::where('slug', 'like', 'demo-%')->count();

        $this->runSeeder(); // should no-op

        $this->assertSame($churchesAfterFirst, Church::where('slug', 'like', 'demo-%')->count());
    }

    public function test_wipe_removes_all_demo_data_but_leaves_real_data_untouched(): void
    {
        // A real (non-demo) church + user that must survive the wipe.
        $realChurch = Church::create(['slug' => 'real-parish', 'name' => 'Real Parish', 'status' => 'active']);
        $realUser = $this->createUser(['email' => 'real.person@example.com']);
        ChurchUser::create(['church_id' => $realChurch->church_id, 'user_id' => $realUser->user_id, 'status' => 'active']);

        $this->runSeeder();
        $this->assertTrue(DemoData::exists());

        $deleted = DemoData::wipe();

        // Demo gone.
        $this->assertFalse(DemoData::exists());
        $this->assertSame(0, Church::where('slug', 'like', 'demo-%')->count());
        $this->assertSame(0, User::where('email', 'like', '%@'.DemoData::emailDomain())->count());
        $this->assertGreaterThan(0, $deleted['churches']);
        $this->assertGreaterThan(0, $deleted['users']);

        // Real data survives.
        $this->assertDatabaseHas('church', ['slug' => 'real-parish']);
        $this->assertDatabaseHas('user', ['email' => 'real.person@example.com']);
        $this->assertTrue(
            ChurchUser::where('church_id', $realChurch->church_id)->where('user_id', $realUser->user_id)->exists()
        );
    }
}
