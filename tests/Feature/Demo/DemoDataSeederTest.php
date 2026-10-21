<?php

namespace Tests\Feature\Demo;

use App\Models\Assignment;
use App\Models\AttendancePolicy;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\FeedbackSurvey;
use App\Models\GradeCategory;
use App\Models\Person;
use App\Models\Priest;
use App\Models\ProjectAssessment;
use App\Models\Session;
use App\Models\User;
use App\Support\Demo\DemoData;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\ServantsPrepDemoCourse;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
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

    private function servantsPrepCourse(): Course
    {
        TenantContext::set(Church::where('slug', 'demo-stmark')->firstOrFail());

        return Course::where('title_en', 'Servants Preparation — Year 1')->firstOrFail();
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

        // Priests, the servants-prep course, and its roster exist under the demo church.
        TenantContext::set($stmark);
        $this->assertGreaterThanOrEqual(2, Priest::count());
        $course = $this->servantsPrepCourse();
        $this->assertSame('servants-prep', $course->service->slug);
        $this->assertSame(23, $course->userCourseRoles()->count()); // 1 course admin + 2 instructors + 20 students
        TenantContext::clear();

        // Credentials were captured with the shared password, one row per persona.
        $this->assertSame(DemoData::password(), $seeder->credentials[0]['password']);
        $emails = array_column($seeder->credentials, 'email');
        foreach (['superadmin', 'admin.stmark', 'service-admin.stmark', 'course-admin.stmark', 'instructor.stmark', 'instructor2.stmark', 'student1.stmark', 'student20.stmark'] as $local) {
            $this->assertContains(DemoData::email($local), $emails);
        }
    }

    public function test_servants_prep_course_carries_a_term_of_activity(): void
    {
        $this->runSeeder();
        $course = $this->servantsPrepCourse();
        $student1 = User::where('email', DemoData::email('student1.stmark'))->firstOrFail();

        // 12 weekly sessions: past weeks closed (except the latest, left open to demo closing).
        $sessions = Session::where('course_id', $course->course_id)->orderBy('week_number')->get();
        $this->assertCount(ServantsPrepDemoCourse::WEEKS, $sessions);
        $closed = $sessions->filter(fn (Session $s) => $s->attendance_closed_at !== null);
        $this->assertCount(ServantsPrepDemoCourse::PAST_WEEKS - 1, $closed);
        $this->assertTrue($sessions[ServantsPrepDemoCourse::PAST_WEEKS]->session_date->isFuture());

        // Closing filled every enrolled student in, with a realistic status mix.
        $statuses = DB::table('attendance')->whereIn('session_id', $closed->pluck('session_id'))->pluck('status');
        $this->assertSame(20 * $closed->count(), $statuses->count());
        foreach (['Present', 'Late', 'Permission', 'Absent'] as $status) {
            $this->assertContains($status, $statuses->all());
        }

        // Gradebook: weighted categories totalling 100, attendance lines synced at close.
        $this->assertEquals(100, GradeCategory::where('course_id', $course->course_id)->sum('weight_percentage'));
        $attendanceCategory = GradeCategory::where('course_id', $course->course_id)->where('type', 'attendance')->firstOrFail();
        $this->assertSame($closed->count(), $attendanceCategory->items()->count());

        // Mid-term totals give a believable spread: most of the class passes, not everyone.
        $course->load('gradeCategories.items.grades');
        $passing = User::where('email', 'like', 'student%.stmark@'.DemoData::emailDomain())->get()
            ->filter(fn (User $u) => $course->studentTotalGrade($u->user_id) >= $course->effectivePassingPercentage())
            ->count();
        $this->assertGreaterThanOrEqual(10, $passing);
        $this->assertLessThan(20, $passing);

        // Quizzes graded through the real grading path; only the first is announced.
        $quizzes = Exam::where('course_id', $course->course_id)->where('exam_type', Exam::TYPE_QUIZ)->orderBy('exam_id')->get();
        $this->assertCount(2, $quizzes);
        $this->assertNotNull($quizzes[0]->results_announced_at);
        $this->assertNull($quizzes[1]->results_announced_at);
        $this->assertGreaterThan(10, ExamResult::where('exam_id', $quizzes[0]->exam_id)->where('status', ExamResult::STATUS_GRADED)->count());
        $this->assertTrue(Exam::where('course_id', $course->course_id)->where('exam_type', Exam::TYPE_EXAM)->exists());

        // Assignments: one graded, one awaiting grading, one upcoming.
        $assignments = Assignment::where('course_id', $course->course_id)->orderBy('assignment_id')->get();
        $this->assertCount(3, $assignments);
        $this->assertGreaterThan(0, $assignments[0]->submissions()->whereNotNull('graded_at')->count());
        $this->assertGreaterThan(0, $assignments[1]->submissions()->whereNull('graded_at')->count());
        $this->assertSame(0, $assignments[2]->submissions()->count());

        // Surveys: one closed with responses, one open that student #1 can still answer.
        $surveys = FeedbackSurvey::where('course_id', $course->course_id)->orderBy('survey_id')->get();
        $this->assertSame(FeedbackSurvey::STATUS_CLOSED, $surveys[0]->status);
        $this->assertGreaterThan(10, $surveys[0]->submissions()->count());
        $this->assertTrue($surveys[1]->isOpen());
        $this->assertFalse($surveys[1]->submissions()->where('user_id', $student1->user_id)->exists());

        $this->assertTrue(ProjectAssessment::where('course_id', $course->course_id)->where('is_published', true)->exists());
    }

    public function test_demo_people_stay_out_of_tenant_zero(): void
    {
        $this->runSeeder();

        $personIds = User::where('email', 'like', '%@'.DemoData::emailDomain())->pluck('person_id')->filter();
        $this->assertNotEmpty($personIds);
        $this->assertSame(0, Person::withoutTenancy()
            ->whereIn('person_id', $personIds)
            ->whereNotIn('church_id', DemoData::churchIds())
            ->count());
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

        // Real data survives, and the platform attendance policy is not swept with the demo.
        $this->assertDatabaseHas('church', ['slug' => 'real-parish']);
        $this->assertDatabaseHas('user', ['email' => 'real.person@example.com']);
        $this->assertTrue(
            ChurchUser::where('church_id', $realChurch->church_id)->where('user_id', $realUser->user_id)->exists()
        );
        $this->assertTrue(AttendancePolicy::withoutTenancy()->whereKey(1)->exists());
    }

    public function test_reseed_and_wipe_leave_no_orphan_rows(): void
    {
        // A first cycle materialises platform-global rows (permissions, templates, policy).
        $this->runSeeder();
        DemoData::wipe();
        $before = $this->tableCounts();

        $this->runSeeder();
        DemoData::wipe();

        $this->assertSame($before, $this->tableCounts());
    }

    /** @return array<string, int> row count per table; the audit trail is append-only by design. */
    private function tableCounts(): array
    {
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn (string $table) => in_array($table, ['activity_logs', 'migrations'], true))
            ->sort()
            ->values();

        return $tables->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
    }
}
