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

    protected function createRole(string $name): Role
    {
        return Role::create([
            'role_name' => $name,
            'role_decription' => substr($name, 0, 25),
        ]);
    }

    protected function createCourse(array $overrides = []): Course
    {
        static $courseCounter = 0;
        $courseCounter++;

        return Course::create(array_merge([
            'title' => 'Course '.$courseCounter,
            'description' => 'Test course',
            'year' => 2026,
        ], $overrides));
    }

    protected function assignCourseRole(User $user, Course $course, Role $role): void
    {
        UserCourseRole::create([
            'user_id' => $user->user_id,
            'course_id' => $course->course_id,
            'role_id' => $role->role_id,
        ]);
    }

    protected function makeEventAdmin(User $user, ?User $assignedBy = null): void
    {
        EventAdmin::create([
            'user_id' => $user->user_id,
            'assigned_by_id' => ($assignedBy ?? $user)->user_id,
            'assigned_at' => now(),
        ]);
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
