<?php

namespace Tests\Support;

use App\Models\Course;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCourseRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class EventModuleTestCase extends TestCase
{
    use RefreshDatabase;

    protected static int $userCounter = 0;

    protected function createUser(array $overrides = []): User
    {
        self::$userCounter++;
        $n = self::$userCounter;

        return User::create(array_merge([
            'first_name' => 'Test',
            'second_name' => 'User',
            'third_name' => 'N',
            'profile_photo' => '',
            'national_id' => sprintf('%014d', $n % 100000000000000),
            'mobile_number' => '01'.str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            'email' => "evt{$n}@example.com",
            'job' => 'Student',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_verified' => true,
            'is_superadmin' => false,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    protected function createRole(string $name): Role
    {
        return Role::create([
            'role_name' => $name,
            'role_decription' => substr($name, 0, 25),
            'slug' => strtolower($name),
            'is_template' => false,
        ]);
    }

    protected function createCourse(array $overrides = []): Course
    {
        static $courseCounter = 0;
        $courseCounter++;

        if (! array_key_exists('service_id', $overrides) && \App\Models\ChurchService::tableReady()) {
            $overrides['service_id'] = \App\Models\ChurchService::ensureDefault()->service_id;
        }

        return Course::create(array_merge([
            'title' => 'Course '.$courseCounter,
            'description' => 'Test course',
            'year' => 2026,
        ], $overrides));
    }

    protected function createService(array $overrides = []): \App\Models\ChurchService
    {
        static $serviceCounter = 0;
        $serviceCounter++;

        return \App\Models\ChurchService::create(array_merge([
            'title' => 'Service '.$serviceCounter,
            'title_en' => 'Service '.$serviceCounter,
            'status' => \App\Models\ChurchService::STATUS_ACTIVE,
            'permissions_version' => 0,
        ], $overrides));
    }

    protected function assignServiceRole(
        User $user,
        \App\Models\ChurchService $service,
        ?Role $role = null,
        bool $asPrimary = false,
        bool $allowCross = false,
    ): \App\Models\UserServiceRole {
        $assigner = app(\App\Services\ServiceRoleAssignmentService::class);
        $role ??= $assigner->memberRoleFor($service);

        return $assigner->assign($user, $service, $role, $asPrimary, $allowCross);
    }

    /**
     * Ensure the user is a member of the course's service, so the service-membership
     * guard on course-role assignment (ServiceRoleAssignmentService) does not reject
     * them. No-op when the service layer is not active or the course has no service.
     */
    protected function ensureServiceMembership(User $user, Course $course): void
    {
        if ($course->service_id && \App\Services\ServiceRoleAssignmentService::schemaReady()) {
            $service = \App\Models\ChurchService::find($course->service_id);
            if ($service && ! app(\App\Services\ServiceRoleAssignmentService::class)->userBelongsToService($user, $service)) {
                $this->assignServiceRole($user, $service, allowCross: true);
            }
        }
    }

    protected function assignCourseRole(User $user, Course $course, Role $role): void
    {
        $this->ensureServiceMembership($user, $course);

        UserCourseRole::updateOrCreate(
            ['user_id' => $user->user_id, 'course_id' => $course->course_id],
            ['role_id' => $role->role_id]
        );
    }

    protected function courseRoleWithPermissions(Course $course, string $slug, array $permissionKeys): Role
    {
        $role = Role::create([
            'role_name' => ucfirst($slug),
            'role_decription' => $slug,
            'slug' => $slug,
            'course_id' => $course->course_id,
        ]);

        $ids = \App\Models\Permission::whereIn('key', $permissionKeys)->pluck('permission_id');
        $role->permissions()->sync($ids);

        return $role;
    }

    protected function makeEventAdmin(User $user, ?User $assignedBy = null): void
    {
        EventAdmin::create([
            'user_id' => $user->user_id,
            'assigned_by_id' => ($assignedBy ?? $user)->user_id,
            'assigned_at' => now(),
        ]);

        app(\App\Services\EventAdminRoleService::class)->grant($user);
    }

    /** @param  array<string, mixed>  $overrides */
    protected function createEvent(User $creator, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Test Event',
            'description' => 'Description',
            'location' => 'Hall A',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'capacity' => 2,
            'visibility' => 'institution',
            'eligible_roles' => [],
            'status' => Event::STATUS_PUBLISHED,
            'check_in_token' => Event::generateCheckInToken(),
            'created_by_id' => $creator->user_id,
        ], $overrides));
    }

    protected function seedBasicRoles(): array
    {
        return [
            'student' => $this->createRole('student'),
            'admin' => $this->createRole('admin'),
        ];
    }
}
