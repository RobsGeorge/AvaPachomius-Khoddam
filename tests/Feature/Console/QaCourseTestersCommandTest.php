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

    public function test_unknown_course_id_fails_without_writing(): void
    {
        $this->artisan('qa:course-testers', [
            '--course' => 999999,
            '--domain' => 'missing.test',
        ])->assertFailed();

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@missing.test')->count());
    }

    public function test_invalid_course_tokens_are_rejected(): void
    {
        $this->artisan('qa:course-testers', [
            '--courses' => 'abc,0,-3,1.5',
            '--domain' => 'badid.test',
        ])->assertFailed();

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@badid.test')->count());
    }

    public function test_empty_domain_is_rejected(): void
    {
        $this->artisan('qa:course-testers', [
            '--domain' => ' ',
            '--dry-run' => true,
        ])->assertFailed();
    }

    public function test_domain_with_at_sign_is_rejected(): void
    {
        $this->artisan('qa:course-testers', [
            '--domain' => 'user@evil.test',
            '--dry-run' => true,
        ])->assertFailed();
    }

    public function test_invalid_hostname_domain_is_rejected(): void
    {
        $this->artisan('qa:course-testers', [
            '--domain' => 'not_a_valid_host',
            '--dry-run' => true,
        ])->assertFailed();
    }

    public function test_short_password_is_rejected(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Short Pass Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => (string) $course->course_id,
            '--admins' => '1',
            '--instructors' => '0',
            '--students' => '0',
            '--password' => 'short',
            '--domain' => 'shortpass.test',
        ])->assertFailed();

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@shortpass.test')->count());
    }

    public function test_course_without_required_roles_is_rejected(): void
    {
        // Intentionally skip RoleTemplateService clone — no admin/instructor/student.
        $course = $this->createCourse(['title' => 'QA Bare Course']);

        $this->artisan('qa:course-testers', [
            '--course' => (string) $course->course_id,
            '--domain' => 'noroles.test',
        ])->assertFailed();
    }

    public function test_wipe_with_no_matching_users_succeeds(): void
    {
        $this->artisan('qa:course-testers', [
            '--wipe' => true,
            '--domain' => 'emptywipe.test',
        ])->assertSuccessful();
    }

    public function test_production_with_force_allows_dry_run(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Force Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $previous = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('qa:course-testers', [
                '--course' => $course->course_id,
                '--force' => true,
                '--dry-run' => true,
                '--domain' => 'forceprod.test',
            ])->assertSuccessful();
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertSame(0, User::where('email', 'like', 'qa.matrix.%@forceprod.test')->count());
    }

    public function test_wipe_dry_run_does_not_delete(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Wipe Dry Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 1,
            '--password' => 'QaWipeDry1!',
            '--domain' => 'wipedry.test',
        ])->assertSuccessful();

        $this->assertSame(2, User::where('email', 'like', 'qa.matrix.%@wipedry.test')->count());

        $this->artisan('qa:course-testers', [
            '--wipe' => true,
            '--dry-run' => true,
            '--domain' => 'wipedry.test',
        ])->assertSuccessful();

        $this->assertSame(2, User::where('email', 'like', 'qa.matrix.%@wipedry.test')->count());
    }

    public function test_reprovision_updates_existing_accounts_and_password(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Reprovision Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 1,
            '--password' => 'QaFirstPass1!',
            '--domain' => 'repro.test',
        ])->assertSuccessful();

        $email = 'qa.matrix.admin1@repro.test';
        $firstId = User::where('email', $email)->value('user_id');
        $this->assertNotNull($firstId);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 1,
            '--password' => 'QaSecondPass2!',
            '--domain' => 'repro.test',
        ])->assertSuccessful();

        $user = User::where('email', $email)->firstOrFail();
        $this->assertSame((int) $firstId, (int) $user->user_id);
        $this->assertTrue(Hash::check('QaSecondPass2!', $user->password));
        $this->assertFalse(Hash::check('QaFirstPass1!', $user->password));
        $this->assertSame(2, User::where('email', 'like', 'qa.matrix.%@repro.test')->count());
    }

    public function test_duplicate_course_ids_in_options_are_deduped(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Dedup Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--courses' => $course->course_id.','.$course->course_id.','.$course->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 1,
            '--password' => 'QaDedupPass1!',
            '--domain' => 'dedup.test',
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    public function test_courses_option_takes_precedence_over_course(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $wanted = $this->createCourse(['title' => 'QA Wanted Course']);
        $ignored = $this->createCourse(['title' => 'QA Ignored Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($wanted);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($ignored);

        $this->artisan('qa:course-testers', [
            '--courses' => (string) $wanted->course_id,
            '--course' => (string) $ignored->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 0,
            '--password' => 'QaPreferPass1!',
            '--domain' => 'prefer.test',
        ])->assertSuccessful();

        $admin = User::where('email', 'qa.matrix.admin1@prefer.test')->firstOrFail();
        $courseIds = UserCourseRole::where('user_id', $admin->user_id)->pluck('course_id')->all();
        $this->assertEquals([(int) $wanted->course_id], array_map('intval', $courseIds));
    }

    public function test_auto_generated_password_is_emitted_and_usable(): void
    {
        app(RoleTemplateService::class)->ensureSystemTemplates();
        $course = $this->createCourse(['title' => 'QA Autogen Course']);
        app(RoleTemplateService::class)->cloneTemplatesIntoCourse($course);

        $this->artisan('qa:course-testers', [
            '--course' => $course->course_id,
            '--admins' => 1,
            '--instructors' => 0,
            '--students' => 0,
            '--domain' => 'autogen.test',
        ])
            ->expectsOutputToContain('Shared password:')
            ->assertSuccessful();

        $path = storage_path('app/'.QaCourseTestersService::CREDENTIALS_RELATIVE_PATH);
        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertMatchesRegularExpression('/Shared password:\s+(\S+)/', $contents);
        preg_match('/Shared password:\s+(\S+)/', $contents, $m);
        $password = $m[1];

        $user = User::where('email', 'qa.matrix.admin1@autogen.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_wipe_also_removes_legacy_qa_course_emails(): void
    {
        User::create([
            'first_name' => 'Legacy',
            'second_name' => 'QA',
            'third_name' => 'Course',
            'email' => 'qa.course.admin@legacy.test',
            'password' => Hash::make('LegacyPass1!'),
            'national_id' => '29901019998888',
            'mobile_number' => '01099998888',
            'job' => 'QA',
            'date_of_birth' => '1990-01-01',
            'profile_photo' => '',
            'is_verified' => true,
            'is_superadmin' => false,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
        ]);

        $this->assertSame(1, User::where('email', 'qa.course.admin@legacy.test')->count());

        $this->artisan('qa:course-testers', [
            '--wipe' => true,
            '--domain' => 'legacy.test',
        ])->assertSuccessful();

        $this->assertSame(0, User::where('email', 'qa.course.admin@legacy.test')->count());
    }
}
