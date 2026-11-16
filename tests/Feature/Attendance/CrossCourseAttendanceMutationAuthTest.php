<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Session;
use App\Models\User;
use Tests\Support\EventModuleTestCase;

/**
 * Course-scoped gate for session attendance mutations.
 * Staff of Course A must not mutate/search Course B sessions via
 * fill-missing / store / record (coarse permission:staff / attendance.staff alone).
 */
class CrossCourseAttendanceMutationAuthTest extends EventModuleTestCase
{
    /** @return array{instructor: User, courseA: \App\Models\Course, courseB: \App\Models\Course, sessionA: Session, sessionB: Session, studentA: User, studentB: User, studentRole: \App\Models\Role} */
    private function seedInstructorOnlyOnCourseA(): array
    {
        $courseA = $this->createCourse(['title' => 'Mutation Auth Course A']);
        $courseB = $this->createCourse(['title' => 'Mutation Auth Course B']);

        $instructor = $this->createUser(['email' => 'att-mutation-cross@example.com']);
        $this->assignCourseRole(
            $instructor,
            $courseA,
            $this->courseRoleWithPermissions($courseA, 'instructor', [
                'attendance.record',
                'attendance.edit',
                'attendance.view_all',
            ])
        );

        $studentRole = $this->createRole('Student');
        $studentA = $this->createUser([
            'first_name' => 'Student',
            'second_name' => 'A',
            'email' => 'att-mutation-student-a@example.com',
        ]);
        $studentB = $this->createUser([
            'first_name' => 'Student',
            'second_name' => 'B',
            'email' => 'att-mutation-student-b@example.com',
        ]);
        $this->assignCourseRole($studentA, $courseA, $studentRole);
        $this->assignCourseRole($studentB, $courseB, $studentRole);

        $sessionA = Session::create([
            'course_id' => $courseA->course_id,
            'session_title' => 'Course A Today',
            'session_date' => now()->toDateString(),
        ]);
        $sessionB = Session::create([
            'course_id' => $courseB->course_id,
            'session_title' => 'Course B Today',
            'session_date' => now()->toDateString(),
        ]);

        return compact(
            'instructor',
            'courseA',
            'courseB',
            'sessionA',
            'sessionB',
            'studentA',
            'studentB',
            'studentRole',
        );
    }

    public function test_staff_of_course_a_cannot_fill_missing_on_course_b_session(): void
    {
        [
            'instructor' => $instructor,
            'sessionB' => $sessionB,
            'studentB' => $studentB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->post(route('sessions.attendance.fill-missing', $sessionB))
            ->assertForbidden();

        $this->assertDatabaseMissing('attendance', [
            'session_id' => $sessionB->session_id,
            'user_id' => $studentB->user_id,
        ]);
    }

    public function test_staff_of_course_a_cannot_store_attendance_on_course_b_session(): void
    {
        [
            'instructor' => $instructor,
            'sessionB' => $sessionB,
            'studentB' => $studentB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->postJson(route('sessions.attendance.store', $sessionB), [
                'user_id' => $studentB->user_id,
                'status' => 'Present',
                'allow_non_enrolled' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendance', [
            'session_id' => $sessionB->session_id,
            'user_id' => $studentB->user_id,
        ]);
    }

    public function test_staff_of_course_a_cannot_record_attendance_on_course_b_session(): void
    {
        [
            'instructor' => $instructor,
            'sessionB' => $sessionB,
            'studentB' => $studentB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->post(route('attendance.record', $sessionB), [
                'student_user_id' => $studentB->user_id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendance', [
            'session_id' => $sessionB->session_id,
            'user_id' => $studentB->user_id,
        ]);
    }

    public function test_staff_of_course_a_cannot_search_students_on_course_b_session(): void
    {
        [
            'instructor' => $instructor,
            'sessionB' => $sessionB,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->getJson(route('sessions.attendance.search', $sessionB).'?q=Student')
            ->assertForbidden();
    }

    public function test_staff_of_course_a_can_fill_missing_and_store_on_own_course_session(): void
    {
        [
            'instructor' => $instructor,
            'courseA' => $courseA,
            'sessionA' => $sessionA,
            'studentA' => $studentA,
            'studentRole' => $studentRole,
        ] = $this->seedInstructorOnlyOnCourseA();

        $this->actingAs($instructor)
            ->post(route('sessions.attendance.fill-missing', $sessionA))
            ->assertRedirect(route('attendance.all', [
                'filter_by' => 'session',
                'session_id' => $sessionA->session_id,
            ]));

        $this->assertDatabaseHas('attendance', [
            'session_id' => $sessionA->session_id,
            'user_id' => $studentA->user_id,
            'status' => 'Absent',
            'taken_by_id' => $instructor->user_id,
        ]);

        // Second student for an explicit store success path.
        $studentA2 = $this->createUser([
            'first_name' => 'Student',
            'second_name' => 'A2',
            'email' => 'att-mutation-student-a2@example.com',
        ]);
        $this->assignCourseRole($studentA2, $courseA, $studentRole);

        $this->actingAs($instructor)
            ->postJson(route('sessions.attendance.store', $sessionA), [
                'user_id' => $studentA2->user_id,
                'status' => 'Present',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendance', [
            'session_id' => $sessionA->session_id,
            'user_id' => $studentA2->user_id,
            'status' => 'Present',
            'taken_by_id' => $instructor->user_id,
        ]);
    }

    public function test_staff_of_course_a_can_record_attendance_on_own_course_session(): void
    {
        [
            'instructor' => $instructor,
            'sessionA' => $sessionA,
            'studentA' => $studentA,
        ] = $this->seedInstructorOnlyOnCourseA();

        // Clear any prior rows so recordAttendance creates a Present stamp.
        Attendance::query()->where('session_id', $sessionA->session_id)->delete();

        $this->actingAs($instructor)
            ->post(route('attendance.record', $sessionA), [
                'student_user_id' => $studentA->user_id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance', [
            'session_id' => $sessionA->session_id,
            'user_id' => $studentA->user_id,
            'status' => 'Present',
            'taken_by_id' => $instructor->user_id,
        ]);
    }
}
