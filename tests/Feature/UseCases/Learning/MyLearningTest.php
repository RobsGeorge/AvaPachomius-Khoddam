<?php

namespace Tests\Feature\UseCases\Learning;

use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\CourseGraduation;
use App\Models\CourseGraduationStudent;
use App\Services\MyLearningService;
use Tests\Support\EventModuleTestCase;

/**
 * F-02 unified student "My learning" (UC-GRD/CERT-*, TC-ML-*). Aggregates grades,
 * attendance, and certificates per enrolled course; empty for the unenrolled.
 */
class MyLearningTest extends EventModuleTestCase
{
    public function test_enrolled_student_sees_a_course_card_with_pending_grades(): void
    {
        $course = $this->createCourse(['title' => 'Patristics 101']);
        $student = $this->createUser(['email' => 'ml-student@example.com']);
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $this->actingAs($student->fresh())->get(route('my-learning.index'))
            ->assertOk()
            ->assertSee('Patristics 101')
            ->assertSee(__('my_learning.grades_pending'))
            ->assertSee(route('attendance.user', $student->user_id));
    }

    public function test_unenrolled_user_sees_the_empty_state(): void
    {
        $user = $this->createUser(['email' => 'ml-empty@example.com']);

        $this->actingAs($user)->get(route('my-learning.index'))
            ->assertOk()
            ->assertSee(__('my_learning.empty_heading'));
    }

    public function test_card_surfaces_announced_grade_and_certificate(): void
    {
        $course = $this->createCourse([
            'title' => 'Graduated Course',
            'status' => Course::STATUS_ANNOUNCED,
            'grades_announced_at' => now()->subDay(),
        ]);
        $student = $this->createUser(['email' => 'ml-grad@example.com']);
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $graduation = CourseGraduation::create([
            'course_id' => $course->course_id,
            'announced_at' => now()->subDay(),
            'status' => CourseGraduation::STATUS_FINAL,
            'passing_percentage' => 50,
            'min_attendance_percentage' => 50,
            'max_grace_marks' => 0,
        ]);
        $record = CourseGraduationStudent::create([
            'course_graduation_id' => $graduation->id,
            'user_id' => $student->user_id,
            'final_total_grade' => 88.0,
            'attendance_pct' => 95.0,
            'letter_grade' => 'A',
            'eligible' => true,
            'graduated' => true,
        ]);
        $certificate = CourseCertificate::create([
            'course_graduation_student_id' => $record->id,
            'user_id' => $student->user_id,
            'course_id' => $course->course_id,
            'issued_at' => now(),
        ]);

        $cards = app(MyLearningService::class)->courseCards($student->fresh());
        $this->assertCount(1, $cards);
        $card = $cards[0];

        $this->assertTrue($card['grades_announced']);
        $this->assertTrue($card['graduated']);
        $this->assertSame('A', $card['letter_grade']);
        $this->assertNotNull($card['grades_url']);
        $this->assertSame(
            route('certificates.download', $certificate->fresh()->certificate_uuid),
            $card['certificate_url']
        );
    }
}
