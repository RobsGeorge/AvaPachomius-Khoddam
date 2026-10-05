<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Course;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSurvey;
use App\Models\Module;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CourseContextService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\EventModuleTestCase;

class FeedbackSurveyClickThroughTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_publishing_a_module_survey_notifies_and_announcement_click_opens_the_form(): void
    {
        [$instructor, $student, $survey] = $this->moduleSurveyFixture();
        $this->enrollStudentInSecondCourse($student);

        $this->actingAs($instructor)
            ->post(route('feedback.surveys.publish', $survey))
            ->assertRedirect();

        $survey->refresh();
        $this->assertSame(FeedbackSurvey::STATUS_OPEN, $survey->status);

        $notification = UserNotification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'feedback_survey_open')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame(route('feedback.surveys.show', $survey, false), $notification->action_url);

        $announcement = Announcement::query()->where('survey_id', $survey->survey_id)->first();
        $this->assertNotNull($announcement);
        $this->assertTrue($announcement->isPublished());

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->actingAs($student)
            ->followingRedirects()
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee($survey->title, false)
            ->assertSee(__('pages.submit_feedback'), false);

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->actingAs($student)
            ->followingRedirects()
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee($survey->title, false)
            ->assertSee(__('pages.submit_feedback'), false);
    }

    public function test_open_non_blocking_survey_announcement_notification_opens_the_form(): void
    {
        [$instructor, $student, $course] = $this->staffStudentAndCourse();
        $this->enrollStudentInSecondCourse($student);

        $survey = $this->makeSurvey($instructor, $course, [
            'title' => 'Optional pulse',
            'is_mandatory' => false,
            'status' => FeedbackSurvey::STATUS_OPEN,
            'opened_at' => now(),
        ]);
        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'Comments',
            'order_index' => 1,
            'is_required' => false,
        ]);

        $announcement = Announcement::create([
            'created_by_user_id' => $instructor->user_id,
            'course_id' => $course->course_id,
            'survey_id' => $survey->survey_id,
            'title' => 'Please fill optional feedback',
            'body' => 'Open feedback is available.',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [Announcement::CHANNEL_HOMEPAGE => true],
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        AnnouncementDelivery::create([
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        $notification = UserNotification::create([
            'user_id' => $student->user_id,
            'type' => UserNotification::TYPE_ADMIN_ANNOUNCEMENT,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'action_url' => route('announcements.show', $announcement),
            'source_type' => 'announcement',
            'source_id' => $announcement->announcement_id,
            'metadata' => ['survey_id' => $survey->survey_id],
            'dedupe_key' => "admin_announcement:{$announcement->announcement_id}:user:{$student->user_id}",
        ]);

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->actingAs($student)
            ->followingRedirects()
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Optional pulse', false)
            ->assertSee(__('pages.submit_feedback'), false);
    }

    public function test_announcement_body_survey_url_is_followed_without_course_context(): void
    {
        [$instructor, $student, $survey] = $this->moduleSurveyFixture(status: FeedbackSurvey::STATUS_OPEN);
        $this->enrollStudentInSecondCourse($student);

        $announcement = Announcement::create([
            'created_by_user_id' => $instructor->user_id,
            'course_id' => $survey->course_id,
            'title' => 'Module feedback is open',
            'body' => 'Please answer at '.route('feedback.surveys.show', $survey),
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [Announcement::CHANNEL_HOMEPAGE => true],
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        AnnouncementDelivery::create([
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->actingAs($student)
            ->followingRedirects()
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee($survey->title, false)
            ->assertSee(__('pages.submit_feedback'), false);
    }

    public function test_unrelated_announcement_still_opens_without_redirect_loop(): void
    {
        [$instructor, $student, $course] = $this->staffStudentAndCourse();
        $this->enrollStudentInSecondCourse($student);

        $announcement = Announcement::create([
            'created_by_user_id' => $instructor->user_id,
            'course_id' => $course->course_id,
            'title' => 'Class cancelled',
            'body' => 'Stay home today.',
            'target_mode' => Announcement::TARGET_COURSE,
            'channels' => [Announcement::CHANNEL_HOMEPAGE => true],
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
        AnnouncementDelivery::create([
            'announcement_id' => $announcement->announcement_id,
            'user_id' => $student->user_id,
        ]);

        $this->actingAs($student);
        app(CourseContextService::class)->clearCurrentCourse();

        $this->actingAs($student)
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Stay home today.');
    }

    /**
     * @return array{0: User, 1: User, 2: FeedbackSurvey}
     */
    private function moduleSurveyFixture(string $status = FeedbackSurvey::STATUS_DRAFT): array
    {
        [$instructor, $student, $course] = $this->staffStudentAndCourse();
        $survey = $this->makeSurvey($instructor, $course, [
            'title' => 'End of module survey',
            'status' => $status,
            'is_mandatory' => true,
            'opened_at' => $status === FeedbackSurvey::STATUS_OPEN ? now() : null,
        ]);
        FeedbackQuestion::create([
            'survey_id' => $survey->survey_id,
            'question_type' => FeedbackQuestion::TYPE_TEXT,
            'scope' => FeedbackQuestion::SCOPE_GENERAL,
            'label' => 'How was this module?',
            'order_index' => 1,
            'is_required' => true,
        ]);

        return [$instructor, $student, $survey];
    }

    /**
     * @return array{0: User, 1: User, 2: Course}
     */
    private function staffStudentAndCourse(): array
    {
        $instructorRole = $this->createRole('instructor');
        $studentRole = $this->createRole('student');
        $instructor = $this->createUser(['email' => 'survey-click-instructor@example.com']);
        $student = $this->createUser(['email' => 'survey-click-student@example.com']);
        $course = $this->createCourse(['title' => 'Survey Click Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);
        $this->assignCourseRole($student, $course, $studentRole);

        $module = Module::create(['title' => 'Click Module', 'description' => 'Desc']);
        $course->modules()->attach($module->module_id, [
            'status' => 'ended',
            'feedback_open' => true,
        ]);

        return [$instructor, $student, $course];
    }

    private function enrollStudentInSecondCourse(User $student): Course
    {
        $studentRole = $this->createRole('student');
        $courseB = $this->createCourse(['title' => 'Second Click Course']);
        $this->assignCourseRole($student, $courseB, $studentRole);

        return $courseB;
    }

    private function makeSurvey(User $instructor, Course $course, array $overrides): FeedbackSurvey
    {
        $module = $course->modules()->first();

        return FeedbackSurvey::create(array_merge([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'title' => 'Survey',
            'created_by_user_id' => $instructor->user_id,
            'status' => FeedbackSurvey::STATUS_DRAFT,
            'is_mandatory' => true,
            'is_anonymous' => true,
        ], $overrides));
    }
}
