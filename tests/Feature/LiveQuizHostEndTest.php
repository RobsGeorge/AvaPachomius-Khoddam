<?php

namespace Tests\Feature;

use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Services\LiveQuizSessionService;
use Tests\Support\EventModuleTestCase;

class LiveQuizHostEndTest extends EventModuleTestCase
{
    public function test_host_can_end_session_with_no_participants(): void
    {

        $instructorRole = $this->createRole('instructor');
        $instructor = $this->createUser(['email' => 'live-quiz-host@example.com']);
        $course = $this->createCourse(['title' => 'Live Quiz Course']);
        $this->assignCourseRole($instructor, $course, $instructorRole);

        $quiz = LiveQuiz::create([
            'course_id' => $course->course_id,
            'title' => 'Test Live Quiz',
            'created_by_user_id' => $instructor->user_id,
            'mode' => LiveQuiz::MODE_INDIVIDUAL,
            'status' => LiveQuiz::STATUS_READY,
            'join_code' => 'ABC123',
        ]);

        LiveQuizQuestion::create([
            'live_quiz_id' => $quiz->live_quiz_id,
            'order_index' => 1,
            'question_type' => LiveQuizQuestion::TYPE_TRUE_FALSE,
            'prompt_text' => 'True or false?',
            'time_limit_seconds' => 30,
            'points' => 1,
        ]);

        $session = app(LiveQuizSessionService::class)->startSession($quiz, $instructor->user_id);

        $this->actingAs($instructor)
            ->post(route('live-quiz.host.end', $session))
            ->assertRedirect(route('live-quiz.host.control', $session))
            ->assertSessionHas('success');

        $session->refresh();
        $this->assertSame(LiveQuizSession::STATUS_ENDED, $session->status);
        $this->assertNotNull($session->ended_at);
    }
}
