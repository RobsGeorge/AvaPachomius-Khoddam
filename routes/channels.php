<?php

use App\Models\LiveFeedbackSession;
use App\Models\LiveQuizParticipant;
use App\Models\LiveQuizSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});

Broadcast::channel('live-quiz.{sessionId}', function ($user, $sessionId) {
    if (! $user) {
        return false;
    }

    $session = LiveQuizSession::find($sessionId);

    if (! $session) {
        return false;
    }

    if ((int) $session->host_user_id === (int) $user->user_id) {
        return true;
    }

    return LiveQuizParticipant::query()
        ->where('session_id', $sessionId)
        ->where('user_id', $user->user_id)
        ->exists();
});

Broadcast::channel('live-feedback.{sessionId}', function ($user, $sessionId) {
    if (! $user) {
        return false;
    }

    $session = LiveFeedbackSession::with('course.modules')->find($sessionId);

    if (! $session) {
        return false;
    }

    if ((int) $session->host_user_id === (int) $user->user_id) {
        return true;
    }

    if ($user->isInstructorOrAdmin()) {
        return true;
    }

    if (! $user->isStudent()) {
        return false;
    }

    $enrolled = $user->courses()
        ->where('course.course_id', $session->course_id)
        ->exists();

    if (! $enrolled) {
        return false;
    }

    $module = $session->course?->modules
        ->firstWhere('module_id', $session->module_id);

    return (bool) ($module?->pivot?->feedback_open ?? false);
});
