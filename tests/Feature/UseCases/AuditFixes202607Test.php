<?php

namespace Tests\Feature\UseCases;

use App\Models\Attendance;
use App\Models\Session;
use App\Models\UserNotification;
use Tests\Support\EventModuleTestCase;

/**
 * Regression coverage for the 2026-07 web-surface audit fixes:
 *  - F2/F6 attendance status update is POST-only and scoped to the record's own course.
 *  - F3    permission:staff on a {course} route requires staff *in that course*.
 *  - F4    the announcement inbox is gated on announcement.view, not a role-name check.
 *  - F9    notifications.show only follows same-origin action_urls.
 */
class AuditFixes202607Test extends EventModuleTestCase
{
    // ---- F2 / F6 : attendance status update -------------------------------------------

    private function attendanceRecordInCourse(\App\Models\Course $course, \App\Models\User $taker): Attendance
    {
        $session = Session::create([
            'course_id' => $course->course_id,
            'session_title' => 'Week 1',
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $student = $this->createUser(['email' => 'att-target-'.uniqid().'@example.com']);
        $this->assignCourseRole($student, $course, $this->createRole('Student'));

        return Attendance::create([
            'user_id' => $student->user_id,
            'session_id' => $session->session_id,
            'taken_by_id' => $taker->user_id,
            'status' => 'Present',
            'attendance_time' => now(),
        ]);
    }

    public function test_status_update_is_post_only_and_rejects_get(): void
    {
        $course = $this->createCourse();
        $instructor = $this->createUser(['email' => 'att-instructor@example.com']);
        $role = $this->courseRoleWithPermissions($course, 'instructor', ['attendance.record']);
        $this->assignCourseRole($instructor, $course, $role);
        $attendance = $this->attendanceRecordInCourse($course, $instructor);

        // The former GET mutation route is gone → method not allowed.
        $this->actingAs($instructor)
            ->get('/attendance/update-status/'.$attendance->getKey())
            ->assertStatus(405);
    }

    public function test_staff_can_update_status_for_a_record_in_their_course(): void
    {
        $course = $this->createCourse();
        $instructor = $this->createUser(['email' => 'att-instructor2@example.com']);
        $role = $this->courseRoleWithPermissions($course, 'instructor', ['attendance.record']);
        $this->assignCourseRole($instructor, $course, $role);
        $attendance = $this->attendanceRecordInCourse($course, $instructor);

        $this->actingAs($instructor)
            ->postJson('/attendance/update-status/'.$attendance->getKey(), ['status' => 'Absent'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('Absent', $attendance->fresh()->status);
    }

    public function test_staff_cannot_update_status_for_another_courses_record(): void
    {
        $courseA = $this->createCourse(['title' => 'Course A']);
        $courseB = $this->createCourse(['title' => 'Course B']);

        $instructor = $this->createUser(['email' => 'att-cross@example.com']);
        // Staff only in course A.
        $this->assignCourseRole(
            $instructor,
            $courseA,
            $this->courseRoleWithPermissions($courseA, 'instructor', ['attendance.record'])
        );

        // Record belongs to course B, where the instructor has no role.
        $recordInB = $this->attendanceRecordInCourse($courseB, $this->createUser(['email' => 'att-b-taker@example.com']));

        $this->actingAs($instructor)
            ->postJson('/attendance/update-status/'.$recordInB->getKey(), ['status' => 'Absent'])
            ->assertForbidden();

        $this->assertSame('Present', $recordInB->fresh()->status);
    }

    // ---- F3 : course-scoped permission:staff ------------------------------------------

    public function test_staff_of_one_course_cannot_open_another_courses_staff_page(): void
    {
        $courseA = $this->createCourse(['title' => 'Graduation A']);
        $courseB = $this->createCourse(['title' => 'Graduation B']);

        $instructor = $this->createUser(['email' => 'grad-cross@example.com']);
        $this->assignCourseRole(
            $instructor,
            $courseA,
            $this->courseRoleWithPermissions($courseA, 'instructor', ['graduation.view'])
        );

        // Their own course: allowed.
        $this->actingAs($instructor)
            ->get(route('graduation.show', $courseA))
            ->assertOk();

        // A course they have no role in: forbidden (was previously served).
        $this->actingAs($instructor)
            ->get(route('graduation.show', $courseB))
            ->assertForbidden();
    }

    // ---- F4 : announcement inbox gate --------------------------------------------------

    public function test_announcement_inbox_denied_without_announcement_view(): void
    {
        // A verified user with no course/service roles holds no announcement.view.
        $user = $this->createUser(['email' => 'no-announce@example.com']);

        $this->actingAs($user)
            ->get(route('announcements.index'))
            ->assertForbidden();
    }

    // ---- F9 : notification open-redirect ----------------------------------------------

    public function test_notification_with_external_action_url_is_not_followed(): void
    {
        $user = $this->createUser(['email' => 'notif-redirect@example.com']);

        $notification = UserNotification::create([
            'user_id' => $user->user_id,
            'type' => 'admin_announcement',
            'title' => 'Suspicious',
            'body' => 'external',
            'action_url' => 'https://evil.example/phish',
            'dedupe_key' => 'external-redirect-test:'.$user->user_id,
        ]);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification))
            ->assertRedirect(route('notifications.index'));
    }
}
