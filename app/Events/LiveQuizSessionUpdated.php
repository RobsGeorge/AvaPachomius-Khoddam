<?php

namespace App\Events;

use App\Models\LiveQuizSession;
use App\Services\LiveQuizSessionService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveQuizSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveQuizSession $session
    ) {
        $this->session->loadMissing(['quiz.questions.options', 'participants', 'answers']);
    }

    public function broadcastOn(): array
    {
        return [new Channel('live-quiz.'.$this->session->session_id)];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        $service = app(LiveQuizSessionService::class);
        $currentQuestion = $this->session->currentQuestion();

        return [
            'session_id' => $this->session->session_id,
            'status' => $this->session->status,
            'current_question_index' => $this->session->current_question_index,
            'question_started_at' => optional($this->session->question_started_at)?->toIso8601String(),
            'mode' => $this->session->mode,
            'team_count' => $this->session->team_count,
            'participant_count' => $this->session->participants->count(),
            'leaderboard' => $service->leaderboard($this->session, $this->session->isTeamMode()),
            'current_question' => $currentQuestion ? [
                'question_id' => $currentQuestion->question_id,
                'order_index' => $currentQuestion->order_index,
                'question_type' => $currentQuestion->question_type,
                'prompt_text' => $currentQuestion->prompt_text,
                'prompt_image_path' => $currentQuestion->prompt_image_path,
                'time_limit_seconds' => $currentQuestion->time_limit_seconds,
                'points' => $currentQuestion->points,
                'options' => $currentQuestion->options->map(fn ($opt) => [
                    'option_id' => $opt->option_id,
                    'label_text' => $opt->label_text,
                    'label_image_path' => $opt->label_image_path,
                    'order_index' => $opt->order_index,
                ])->values(),
            ] : null,
            'aggregates' => $currentQuestion
                ? $service->questionAggregates($this->session, $currentQuestion)
                : null,
        ];
    }
}
