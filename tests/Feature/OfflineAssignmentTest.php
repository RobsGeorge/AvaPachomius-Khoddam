<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

class OfflineAssignmentTest extends EventModuleTestCase
{
    public function test_create_offline_assignment_and_mode_immutable_on_update(): void
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $instructorRole = $this->courseRoleWithPermissions($course, 'instructor-offline', [
            'assignment.manage',
            'assignment.view',
            'assignment.grade',
        ]);
        $instructor = $this->createUser(['email' => 'offline-create@example.com']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('assignments.store'), [
                'course_id' => $course->course_id,
                'assignment_name' => 'Offline Research',
                'assignment_description' => 'Hand in paper',
                'total_points' => 20,
                'due_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'delivery_mode' => Assignment::MODE_OFFLINE,
            ])
            ->assertRedirect(route('assignments.index'));

        $assignment = Assignment::where('assignment_name', 'Offline Research')->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->isOffline());

        $this->actingAs($instructor)
            ->put(route('assignments.update', $assignment), [
                'assignment_name' => 'Offline Research Updated',
                'assignment_description' => 'Hand in paper',
                'total_points' => 20,
                'due_date' => now()->addDays(5)->format('Y-m-d\TH:i'),
                'delivery_mode' => Assignment::MODE_ONLINE,
            ])
            ->assertRedirect(route('assignments.index'));

        $assignment->refresh();
        $this->assertSame('Offline Research Updated', $assignment->assignment_name);
        $this->assertTrue($assignment->isOffline());
    }

    public function test_student_cannot_submit_offline_assignment(): void
    {
        Storage::fake('public');
        [$course, $instructor, $student, $assignment] = $this->offlineFixture();

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->post(route('assignments.submit', $assignment), [
                'submission_content' => 'Should fail',
                'file' => UploadedFile::fake()->create('work.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('assignment_submission', [
            'assignment_id' => $assignment->assignment_id,
            'user_id' => $student->user_id,
        ]);
    }

    public function test_mark_received_then_grade_with_feedback_visible_to_student(): void
    {
        [$course, $instructor, $student, $assignment] = $this->offlineFixture();

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('assignments.mark-received', [$assignment, $student]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission = AssignmentSubmission::where('assignment_id', $assignment->assignment_id)
            ->where('user_id', $student->user_id)
            ->first();
        $this->assertNotNull($submission);
        $this->assertNull($submission->points_earned);

        $this->actingAs($instructor)
            ->post(route('assignments.grade', $submission), [
                'points_earned' => 18,
                'feedback' => 'Excellent handwritten work',
            ])
            ->assertRedirect(route('assignments.show', $assignment));

        $submission->refresh();
        $this->assertSame(18, $submission->points_earned);
        $this->assertSame('Excellent handwritten work', $submission->feedback);
        $this->assertNotNull($submission->graded_at);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Excellent handwritten work', false)
            ->assertSee('18', false);
    }

    public function test_remind_unsubmitted_sends_once_per_day(): void
    {
        Mail::fake();
        [$course, $instructor, $student, $assignment] = $this->offlineFixture();
        $submittedStudent = $this->createUser(['email' => 'offline-submitted@example.com']);
        $studentRole = $this->courseRoleWithPermissions($course, 'student-offline-2', [
            'assignment.view',
            'assignment.submit',
        ]);
        $this->assignCourseRole($submittedStudent, $course, $studentRole);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->assignment_id,
            'user_id' => $submittedStudent->user_id,
            'submission_content' => null,
            'submitted_at' => now(),
        ]);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('assignments.remind-unsubmitted', $assignment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $student->user_id,
            'type' => 'assignment_submission_reminder',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $submittedStudent->user_id,
            'type' => 'assignment_submission_reminder',
        ]);

        $countAfterFirst = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'assignment_submission_reminder')
            ->count();

        $this->actingAs($instructor)
            ->post(route('assignments.remind-unsubmitted', $assignment))
            ->assertRedirect();

        $countAfterSecond = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'assignment_submission_reminder')
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_online_submit_and_grade_still_works(): void
    {
        Storage::fake('public');
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $instructorRole = $this->courseRoleWithPermissions($course, 'instructor-online', [
            'assignment.manage',
            'assignment.view',
            'assignment.grade',
        ]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student-online', [
            'assignment.view',
            'assignment.submit',
        ]);
        $instructor = $this->createUser(['email' => 'online-inst@example.com']);
        $student = $this->createUser(['email' => 'online-stu@example.com']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $assignment = Assignment::create([
            'course_id' => $course->course_id,
            'assignment_name' => 'Online Task',
            'assignment_description' => 'Upload PDF',
            'total_points' => 10,
            'due_date' => now()->addWeek(),
            'delivery_mode' => Assignment::MODE_ONLINE,
        ]);

        app(CourseContextService::class)->setCurrentCourse($student, $course->course_id);

        $this->actingAs($student)
            ->post(route('assignments.submit', $assignment), [
                'submission_content' => 'My answer',
                'file' => UploadedFile::fake()->create('essay.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('assignments.show', $assignment));

        $submission = AssignmentSubmission::where('assignment_id', $assignment->assignment_id)
            ->where('user_id', $student->user_id)
            ->first();
        $this->assertNotNull($submission);

        app(CourseContextService::class)->setCurrentCourse($instructor, $course->course_id);

        $this->actingAs($instructor)
            ->post(route('assignments.grade', $submission), [
                'points_earned' => 9,
                'feedback' => 'Good',
            ])
            ->assertRedirect(route('assignments.show', $assignment));

        $submission->refresh();
        $this->assertSame(9, $submission->points_earned);
        $this->assertNotNull($submission->graded_at);
    }

    /**
     * @return array{0: Course, 1: \App\Models\User, 2: \App\Models\User, 3: Assignment}
     */
    private function offlineFixture(): array
    {
        $course = $this->createCourse(['status' => Course::STATUS_ACTIVE]);
        $instructorRole = $this->courseRoleWithPermissions($course, 'instructor-off-'.uniqid(), [
            'assignment.manage',
            'assignment.view',
            'assignment.grade',
        ]);
        $studentRole = $this->courseRoleWithPermissions($course, 'student-off-'.uniqid(), [
            'assignment.view',
            'assignment.submit',
        ]);
        $instructor = $this->createUser(['email' => 'offline-inst-'.uniqid().'@example.com']);
        $student = $this->createUser(['email' => 'offline-stu-'.uniqid().'@example.com']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $assignment = Assignment::create([
            'course_id' => $course->course_id,
            'assignment_name' => 'Offline Hand-in',
            'assignment_description' => 'Paper copy',
            'total_points' => 20,
            'due_date' => now()->addWeek(),
            'delivery_mode' => Assignment::MODE_OFFLINE,
        ]);

        return [$course, $instructor, $student, $assignment];
    }
}
