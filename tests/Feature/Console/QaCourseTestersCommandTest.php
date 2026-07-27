<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Models\UserCourseRole;
use App\Services\CoursePermissionResolver;
use App\Services\QaCourseTestersService;
use App\Services\RoleTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Support\EventModuleTestCase;

class QaCourseTestersCommandTest extends EventModuleTestCase
{
    public function test_provisions_small_matrix_and_writes_credentials(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Target Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $password = 'QaTestPass1!';

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 1,
            '--students' => 1,
            '--password' => $password,
            '--domain' => 'example.test',
        ])->assertSuccessful();

        $qa = app(QaCourseTestersService::class);
        foreach (['admin', 'instructor', 'student'] as $persona) {
            $email = $qa->emailForPersona($persona, 1, 'example.test');
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, $email);
            $this->assertTrue($user->is_verified);
            $this->assertTrue((bool) $user->registration_completed);
            $this->assertTrue($user->isApplicationApproved());
            $this->assertFalse((bool) $user->is_superadmin);
            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertTrue(Auth::attempt(['email' => $email, 'password' => $password]));
            Auth::logout();
        }

        $path = storage_path('app/'.QaCourseTestersService::CREDENTIALS_RELATIVE_PATH);
        $this->assertFileExists($path);
        $this->assertStringContainsString($password, (string) file_get_contents($path));
    }

    public function test_multi_course_matrix_distributes_students_and_multi_admins(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $courseA = $this->createCourse(['title' => 'Matrix Course A']);
        $courseB = $this->createCourse(['title' => 'Matrix Course B']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($courseA);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($courseB);

        $this->artisan('qa:course-testers', [
            '--courses' => $courseA->course_id.','.$courseB->course_id,
            '--admins' => 3,
            '--instructors' => 2,
            '--students' => 12,
            '--password' => 'QaMatrixPass1!',
            '--domain' => 'matrix.test',
        ])->assertSuccessful();

        $this->assertSame(17, User::where('email', 'like', 'qa.matrix.%@matrix.test')->count());

        $admin1 = User::where('email', 'qa.matrix.admin1@matrix.test')->firstOrFail();
        $adminCourses = UserCourseRole::where('user_id', $admin1->user_id)->pluck('course_id')->sort()->values();
        $this->assertEqualsCanonicalizing(
            [(int) $courseA->course_id, (int) $courseB->course_id],
            $adminCourses->map(fn ($id) => (int) $id)->all()
        );

        $studentCourses = UserCourseRole::query()
            ->whereIn('user_id', User::where('email', 'like', 'qa.matrix.student%@matrix.test')->pluck('user_id'))
            ->pluck('course_id')
            ->unique()
            ->sort()
            ->values();
        $this->assertEqualsCanonicalizing(
            [(int) $courseA->course_id, (int) $courseB->course_id],
            $studentCourses->map(fn ($id) => (int) $id)->all()
        );

        $dual = UserCourseRole::query()
            ->whereIn('user_id', User::where('email', 'like', 'qa.matrix.student%@matrix.test')->pluck('user_id'))
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->having('c', '>', 1)
            ->count();
        $this->assertGreaterThan(0, $dual);

        $resolver = app(CoursePermissionResolver::class);
        $this->assertTrue($resolver->canInCourse($admin1, 'role.manage', $courseA));
        $this->assertTrue($resolver->canInCourse($admin1, 'role.manage', $courseB));

        $student1 = User::where('email', 'qa.matrix.student1@matrix.test')->firstOrFail();
        $this->assertTrue($resolver->canInCourse($student1, 'assignment.submit', $courseA));
        $this->assertFalse($resolver->canInCourse($student1, 'role.manage', $courseA));
    }

    public function test_smoke_matrix_allow_deny_for_provisioned_roles(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Smoke Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 1,
            '--students' => 1,
            '--password' => 'QaSmokePass1!',
            '--domain' => 'smoke.test',
        ])->assertSuccessful();

        $qa = app(QaCourseTestersService::class);
        $resolver = app(CoursePermissionResolver::class);

        $admin = User::where('email', $qa->emailForPersona('admin', 1, 'smoke.test'))->firstOrFail();
        $instructor = User::where('email', $qa->emailForPersona('instructor', 1, 'smoke.test'))->firstOrFail();
        $student = User::where('email', $qa->emailForPersona('student', 1, 'smoke.test'))->firstOrFail();

        $this->assertTrue($resolver->canInCourse($student, 'curriculum.view', $course));
        $this->assertTrue($resolver->canInCourse($student, 'assignment.submit', $course));
        $this->assertFalse($resolver->canInCourse($student, 'role.manage', $course));
        $this->assertFalse($resolver->canInCourse($student, 'assignment.manage', $course));

        $this->actingAs($student)
            ->get(route('roles.hub', ['course' => $course->course_id, 'section' => 'course']))
            ->assertForbidden();

        $this->assertTrue($resolver->canInCourse($instructor, 'assignment.manage', $course));
        $this->assertFalse($resolver->canInCourse($instructor, 'role.manage', $course));

        $this->actingAs($instructor)
            ->get(route('roles.hub', ['course' => $course->course_id, 'section' => 'course']))
            ->assertForbidden();

        $this->assertTrue($resolver->canInCourse($admin, 'role.manage', $course));

        $this->actingAs($admin)
            ->get(route('roles.hub', ['course' => $course->course_id, 'section' => 'course']))
            ->assertOk();
    }

    public function test_wipe_removes_qa_accounts(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Wipe Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 2,
            '--instructors' => 1,
            '--students' => 3,
            '--password' => 'QaWipePass1!',
            '--domain' => 'wipe.test',
        ])->assertSuccessful();

        $this->assertSame(6, User::where('email', 'like', 'qa.matrix.%@wipe.test')->count());

        $this->artisan('qa:course-testers', [
            '--wipe' => true,
            '--domain' => 'wipe.test',
        ])->assertSuccessful();

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@wipe.test')->count());
        $this->assertFileDoesNotExist(storage_path('app/'.QaCourseTestersService::CREDENTIALS_RELATIVE_PATH));
    }

    public function test_refuses_production_without_force(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('qa:course-testers')
                ->assertFailed();
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_dry_run_prints_plan_without_creating_users(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Dry Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 3,
            '--students' => 12,
            '--dry-run' => true,
            '--domain' => 'dry.test',
        ])->assertSuccessful();

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@dry.test')->count());
    }
}
