<?php

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
