<?php

namespace App\Services;

use App\Models\Course;
use App\Models\LiveFeedbackSession;
use App\Models\ModuleFeedback;
use App\Models\User;
use Illuminate\Support\Collection;

class MandatoryFeedbackService
{
    public function __construct(
        private LiveFeedbackSessionService $liveFeedbackSessions
    ) {}

    public function pendingForUser(User $user): Collection
    {
        if (! $user->isStudent()) {
            return collect();
        }

        $courseIds = $user->courses()->pluck('course.course_id');

        if ($courseIds->isEmpty()) {
            return collect();
        }

        $pending = collect();

        foreach ($courseIds as $courseId) {
            $course = Course::with('modules')->find($courseId);
            if (! $course) {
                continue;
            }

            foreach ($course->modules as $module) {
                if (! (bool) ($module->pivot->feedback_open ?? false)) {
                    continue;
                }

                $submitted = ModuleFeedback::where('user_id', $user->user_id)
                    ->where('course_id', $courseId)
                    ->where('module_id', $module->module_id)
                    ->exists();

                if ($submitted) {
                    continue;
                }

                $liveSession = $this->liveFeedbackSessions->activeSessionForModule(
                    (int) $courseId,
                    (int) $module->module_id
                );

                if ($liveSession && ! $liveSession->mandatory_gate) {
                    continue;
                }

                $pending->push([
                    'course_id' => (int) $courseId,
                    'module_id' => (int) $module->module_id,
                    'course_title' => $course->title,
                    'module_name' => $module->title ?? __('pages.module'),
                    'live_session_id' => $liveSession?->session_id,
                ]);
            }
        }

        return $pending;
    }

    public function hasPending(User $user): bool
    {
        return $this->pendingForUser($user)->isNotEmpty();
    }

    public function firstPending(User $user): ?array
    {
        return $this->pendingForUser($user)->first();
    }
}
