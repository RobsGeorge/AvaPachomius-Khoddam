<?php

namespace App\Services;

use App\Events\LiveFeedbackSessionUpdated;
use App\Models\Course;
use App\Models\LiveFeedbackResponse;
use App\Models\LiveFeedbackSession;
use App\Models\Module;
use App\Models\ModuleFeedback;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LiveFeedbackSessionService
{
    public function startForModule(Course $course, Module $module, int $hostUserId, bool $liveMode = true): LiveFeedbackSession
    {
        LiveFeedbackSession::where('course_id', $course->course_id)
            ->where('module_id', $module->module_id)
            ->where('status', LiveFeedbackSession::STATUS_LIVE)
            ->update([
                'status' => LiveFeedbackSession::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

        $session = LiveFeedbackSession::create([
            'course_id' => $course->course_id,
            'module_id' => $module->module_id,
            'host_user_id' => $hostUserId,
            'status' => LiveFeedbackSession::STATUS_LIVE,
            'mandatory_gate' => true,
            'live_mode' => $liveMode,
            'current_step' => 0,
            'started_at' => now(),
        ]);

        $this->broadcast($session);

        return $session;
    }

    public function closeSession(LiveFeedbackSession $session): LiveFeedbackSession
    {
        $session->update([
            'status' => LiveFeedbackSession::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        $this->broadcast($session->fresh('responses'));

        return $session;
    }

    public function savePartial(LiveFeedbackSession $session, int $userId, array $data): LiveFeedbackResponse
    {
        $response = LiveFeedbackResponse::updateOrCreate(
            ['session_id' => $session->session_id, 'user_id' => $userId],
            $data
        );

        $this->broadcast($session->fresh('responses'));

        return $response;
    }

    public function submit(LiveFeedbackSession $session, int $userId, array $data): LiveFeedbackResponse
    {
        return DB::transaction(function () use ($session, $userId, $data) {
            $response = LiveFeedbackResponse::updateOrCreate(
                ['session_id' => $session->session_id, 'user_id' => $userId],
                array_merge($data, [
                    'submitted' => true,
                    'submitted_at' => now(),
                ])
            );

            ModuleFeedback::updateOrCreate(
                [
                    'user_id' => $userId,
                    'course_id' => $session->course_id,
                    'module_id' => $session->module_id,
                ],
                $this->moduleFeedbackPayload($data)
            );

            $this->broadcast($session->fresh('responses'));

            return $response;
        });
    }

    public function aggregates(LiveFeedbackSession $session): array
    {
        $keys = ['lecture', 'speaker', 'workshop', 'timing', 'content'];
        $result = [];

        foreach ($keys as $key) {
            $column = $key.'_rating';
            $rows = $session->responses()
                ->whereNotNull($column)
                ->selectRaw("$column as rating, COUNT(*) as total")
                ->groupBy($column)
                ->pluck('total', 'rating');

            $total = $rows->sum();
            $average = $total > 0
                ? round($rows->reduce(fn ($carry, $count, $rating) => $carry + ($rating * $count), 0) / $total, 2)
                : null;

            $result[$key] = [
                'distribution' => $rows->map(fn ($count, $rating) => [
                    'rating' => (int) $rating,
                    'count' => (int) $count,
                ])->values()->all(),
                'average' => $average,
                'responses' => $total,
            ];
        }

        $result['submitted_count'] = $session->responses()->where('submitted', true)->count();
        $result['partial_count'] = $session->responses()->where('submitted', false)->count();

        return $result;
    }

    public function activeSessionForModule(int $courseId, int $moduleId): ?LiveFeedbackSession
    {
        return LiveFeedbackSession::where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->where('status', LiveFeedbackSession::STATUS_LIVE)
            ->latest('session_id')
            ->first();
    }

    public function broadcast(LiveFeedbackSession $session): void
    {
        event(new LiveFeedbackSessionUpdated($session, $this->aggregates($session)));
    }

    private function moduleFeedbackPayload(array $data): array
    {
        $fields = [
            'lecture_rating', 'lecture_comments',
            'speaker_rating', 'speaker_comments',
            'workshop_rating', 'workshop_comments',
            'timing_rating', 'timing_comments',
            'content_rating', 'content_comments',
            'notes',
        ];

        return collect($fields)
            ->mapWithKeys(fn ($field) => [$field => $data[$field] ?? null])
            ->all();
    }
}
