@extends('layouts.app')

@section('title', $liveQuiz->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <h1 class="page-title">{{ $liveQuiz->title }}</h1>
    <p class="text-muted-theme">{{ __('pages.live_quiz_code') }}: <strong>{{ $liveQuiz->join_code }}</strong></p>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('live-quiz.builder', $liveQuiz) }}" class="btn btn-primary">{{ __('pages.live_quiz_builder') }}</a>
        <form method="POST" action="{{ route('live-quiz.host.start', $liveQuiz) }}">@csrf
            <button class="btn btn-success">{{ __('pages.live_quiz_start_session') }}</button>
        </form>
    </div>
</div>
@endsection
