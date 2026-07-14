<?php

namespace Tests\Feature\UseCases\Calendar;

use App\Models\Exam;
use App\Models\ExamSchedule;
use App\Models\Session;
use App\Services\CalendarService;
use Tests\Support\EventModuleTestCase;

/**
 * F-06 personal iCalendar export (UC-CUR/EXAM/EVT, TC-CAL-*). Verifies the feed
 * aggregates a student's upcoming sessions and exams into a valid .ics download.
 */
class CalendarExportTest extends EventModuleTestCase
{
    public function test_ics_feed_includes_upcoming_exams_and_sessions_for_a_student(): void
    {
        $course = $this->createCourse(['title' => 'Liturgics']);
        $student = $this->createUser(['email' => 'cal-student@example.com']);
        $this->assignCourseRole($student, $course, $this->createRole('student'));

        $exam = Exam::create([
            'course_id' => $course->course_id, 'exam_name' => 'Final', 'exam_type' => Exam::TYPE_EXAM,
            'delivery_mode' => Exam::MODE_ONLINE, 'duration_minutes' => 60, 'scheduled_date' => now()->addDays(3),
            'total_points' => 10, 'passing_score' => 5, 'is_published' => true,
        ]);
        ExamSchedule::create([
            'exam_id' => $exam->exam_id, 'scheduled_date' => now()->addDays(3), 'is_completed' => false,
        ]);
        Session::create([
            'course_id' => $course->course_id, 'week_number' => 1, 'session_title' => 'Week 1',
            'session_date' => now()->addDays(1)->toDateString(), 'session_start_time' => '18:00',
        ]);

        $ics = app(CalendarService::class)->icsForUser($student->fresh());

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('Final', $ics);
        $this->assertStringContainsString('Week 1', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertStringContainsString("\r\n", $ics); // RFC 5545 CRLF line endings
    }

    public function test_calendar_download_route_serves_an_ics_attachment(): void
    {
        $student = $this->createUser(['email' => 'cal-dl@example.com']);

        $response = $this->actingAs($student)->get(route('calendar.ics'));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', (string) $response->getContent());
    }

    public function test_calendar_requires_authentication(): void
    {
        $this->get(route('calendar.ics'))->assertRedirect(route('login'));
    }

    public function test_feed_includes_course_peers_and_staff_birthdays_but_not_self_or_outsiders(): void
    {
        $course = $this->createCourse();
        $otherCourse = $this->createCourse();
        $studentRole = $this->createRole('student');
        $instructorRole = $this->createRole('instructor');

        $viewer = $this->createUser(['email' => 'bd-viewer@example.com', 'date_of_birth' => '2001-05-05']);
        $this->assignCourseRole($viewer, $course, $studentRole);

        $peer = $this->createUser(['email' => 'bd-peer@example.com', 'date_of_birth' => '2002-03-14']);
        $this->assignCourseRole($peer, $course, $studentRole);

        $instructor = $this->createUser(['email' => 'bd-instructor@example.com', 'date_of_birth' => '1990-07-20']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $outsider = $this->createUser(['email' => 'bd-outsider@example.com', 'date_of_birth' => '2000-01-01']);
        $this->assignCourseRole($outsider, $otherCourse, $studentRole);

        $ics = app(CalendarService::class)->icsForUser($viewer->fresh());

        // Peer (student) and instructor (staff) of the shared course appear, as yearly events.
        $this->assertStringContainsString('birthday-'.$peer->user_id.'@khedma', $ics);
        $this->assertStringContainsString('birthday-'.$instructor->user_id.'@khedma', $ics);
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:', $ics);

        // Not the viewer's own birthday, and not someone from another course.
        $this->assertStringNotContainsString('birthday-'.$viewer->user_id.'@khedma', $ics);
        $this->assertStringNotContainsString('birthday-'.$outsider->user_id.'@khedma', $ics);
    }
}
