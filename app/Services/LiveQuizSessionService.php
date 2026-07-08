<?php

namespace App\Services;

use App\Events\LiveQuizSessionUpdated;
use App\Models\LiveQuiz;
use App\Models\LiveQuizAnswer;
use App\Models\LiveQuizParticipant;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LiveQuizSessionService
{
    public function startSession(LiveQuiz $quiz, int $hostUserId): LiveQuizSession
    {
        $quiz->load('questions.options');

        if ($quiz->questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => [__('pages.live_quiz_no_questions')],
            ]);
        }

        return LiveQuizSession::create([
            'live_quiz_id' => $quiz->live_quiz_id,
            'host_user_id' => $hostUserId,
            'join_code' => LiveJoinCodeService::generate(),
            'status' => LiveQuizSession::STATUS_LOBBY,
            'mode' => $quiz->mode,
            'team_count' => $quiz->team_count,
            'started_at' => now(),
        ]);
    }

    public function joinSession(LiveQuizSession $session, int $userId, string $displayName, ?int $teamNumber = null): LiveQuizParticipant
    {
        if ($session->status === LiveQuizSession::STATUS_ENDED) {
            throw ValidationException::withMessages([
                'session' => [__('pages.live_quiz_session_ended')],
            ]);
        }

        if ($session->isTeamMode()) {
            if ($teamNumber === null || $teamNumber < 1 || $teamNumber > (int) $session->team_count) {
                throw ValidationException::withMessages([
                    'team_number' => [__('pages.live_quiz_pick_team')],
                ]);
            }
        }

        return LiveQuizParticipant::firstOrCreate(
            ['session_id' => $session->session_id, 'user_id' => $userId],
            [
                'display_name' => $displayName,
                'team_number' => $session->isTeamMode() ? $teamNumber : null,
                'joined_at' => now(),
            ]
        );
    }

    public function launchQuestion(LiveQuizSession $session, int $orderIndex): LiveQuizSession
    {
        $question = $session->quiz->questions()->where('order_index', $orderIndex)->firstOrFail();

        $session->update([
            'status' => LiveQuizSession::STATUS_QUESTION,
            'current_question_index' => $orderIndex,
            'question_started_at' => now(),
        ]);

        $this->broadcast($session->fresh(['quiz.questions.options', 'participants']));

        return $session;
    }

    public function showResults(LiveQuizSession $session): LiveQuizSession
    {
        $session->update(['status' => LiveQuizSession::STATUS_RESULTS]);
        $this->broadcast($session->fresh(['quiz.questions.options', 'participants', 'answers']));

        return $session;
    }

    public function endSession(LiveQuizSession $session): LiveQuizSession
    {
        $session->update([
            'status' => LiveQuizSession::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        $this->broadcast($session->fresh(['quiz.questions.options', 'participants', 'answers']));

        return $session;
    }

    public function submitAnswer(
        LiveQuizSession $session,
        LiveQuizParticipant $participant,
        LiveQuizQuestion $question,
        int $optionId
    ): LiveQuizAnswer {
        if ($session->status !== LiveQuizSession::STATUS_QUESTION) {
            throw ValidationException::withMessages([
                'session' => [__('pages.live_quiz_not_accepting_answers')],
            ]);
        }

        if ((int) $session->current_question_index !== (int) $question->order_index) {
            throw ValidationException::withMessages([
                'question' => [__('pages.live_quiz_wrong_question')],
            ]);
        }

        $option = $question->options()->where('option_id', $optionId)->firstOrFail();
        $points = $option->is_correct ? (float) $question->points : 0.0;

        return DB::transaction(function () use ($session, $participant, $question, $option, $points) {
            $answer = LiveQuizAnswer::updateOrCreate(
                [
                    'participant_id' => $participant->participant_id,
                    'question_id' => $question->question_id,
                ],
                [
                    'session_id' => $session->session_id,
                    'option_id' => $option->option_id,
                    'is_correct' => $option->is_correct,
                    'points_earned' => $points,
                    'answered_at' => now(),
                ]
            );

            $total = $participant->answers()->sum('points_earned');
            $participant->update(['score' => (int) round($total)]);

            $this->broadcast($session->fresh(['quiz.questions.options', 'participants', 'answers']));

            return $answer;
        });
    }

    public function leaderboard(LiveQuizSession $session, bool $teamMode = false): array
    {
        if ($teamMode) {
            return $session->participants()
                ->selectRaw('team_number, SUM(score) as total_score, COUNT(*) as member_count')
                ->groupBy('team_number')
                ->orderByDesc('total_score')
                ->get()
                ->map(fn ($row) => [
                    'team_number' => (int) $row->team_number,
                    'score' => (int) $row->total_score,
                    'members' => (int) $row->member_count,
                ])
                ->all();
        }

        return $session->participants()
            ->orderByDesc('score')
            ->orderBy('display_name')
            ->get(['participant_id', 'display_name', 'score', 'team_number'])
            ->map(fn ($p) => [
                'participant_id' => $p->participant_id,
                'display_name' => $p->display_name,
                'score' => (int) $p->score,
                'team_number' => $p->team_number,
            ])
            ->all();
    }

    public function questionAggregates(LiveQuizSession $session, LiveQuizQuestion $question): array
    {
        $counts = $session->answers()
            ->where('question_id', $question->question_id)
            ->selectRaw('option_id, COUNT(*) as total')
            ->groupBy('option_id')
            ->pluck('total', 'option_id');

        $options = $question->options->map(fn ($opt) => [
            'option_id' => $opt->option_id,
            'label_text' => $opt->label_text,
            'label_image_path' => $opt->label_image_path,
            'is_correct' => $opt->is_correct,
            'count' => (int) ($counts[$opt->option_id] ?? 0),
        ]);

        $totalAnswers = $counts->sum();
        $participantCount = $session->participants()->count();

        return [
            'options' => $options,
            'total_answers' => $totalAnswers,
            'participant_count' => $participantCount,
        ];
    }

    public function broadcast(LiveQuizSession $session): void
    {
        try {
            event(new LiveQuizSessionUpdated($session));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
