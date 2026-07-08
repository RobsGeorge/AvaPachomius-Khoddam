<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->user_id === (int) $id;
});

Broadcast::channel('live-quiz.{sessionId}', function ($user, $sessionId) {
    return $user !== null;
});

Broadcast::channel('live-feedback.{sessionId}', function ($user, $sessionId) {
    return $user !== null;
});
