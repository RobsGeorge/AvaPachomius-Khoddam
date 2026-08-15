<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\Support\EventModuleTestCase;

/**
 * RT-ISO-#6 / #7 — the stub UserController resource returned empty 200s to any
 * authenticated user. The resource is gone; leftover URLs must never serve 200.
 */
class UsersStubRemovedTest extends EventModuleTestCase
{
    public function test_users_resource_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('users.index'));
        $this->assertFalse(Route::has('users.show'));
        $this->assertFalse(Route::has('users.create'));
        $this->assertFalse(Route::has('users.store'));
        $this->assertFalse(Route::has('users.edit'));
        $this->assertFalse(Route::has('users.update'));
        $this->assertFalse(Route::has('users.destroy'));
    }

    public function test_guest_cannot_open_stub_users_urls(): void
    {
        $this->get('/users')->assertStatus(404);
        $this->get('/users/1')->assertStatus(404);
    }

    public function test_student_never_receives_empty_200_on_stub_users_urls(): void
    {
        $student = $this->createUser(['email' => 'users-stub-student@example.com']);
        $this->actingAs($student);

        $this->get('/users')->assertStatus(404);
        $this->get('/users/'.$student->user_id)->assertStatus(404);
    }

    public function test_instructor_never_receives_empty_200_on_stub_users_urls(): void
    {
        $instructor = $this->createUser(['email' => 'users-stub-instructor@example.com']);
        $course = $this->createCourse(['title' => 'Users Stub Course']);
        $this->assignCourseRole($instructor, $course, $this->createRole('instructor'));

        $this->actingAs($instructor);

        $this->get('/users')->assertStatus(404);
        $this->get('/users/'.$instructor->user_id)->assertStatus(404);
    }
}
