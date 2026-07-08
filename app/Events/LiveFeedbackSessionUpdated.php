<?php

namespace App\Events;

use App\Models\LiveFeedbackSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveFeedbackSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveFeedbackSession $session,
        public array $aggregates
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('live-feedback.'.$this->session->session_id)];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->session_id,
            'status' => $this->session->status,
            'current_step' => $this->session->current_step,
            'submitted_count' => $this->aggregates['submitted_count'] ?? 0,
            'partial_count' => $this->aggregates['partial_count'] ?? 0,
            'aggregates' => collect($this->aggregates)
                ->except(['submitted_count', 'partial_count'])
                ->all(),
        ];
    }
}
