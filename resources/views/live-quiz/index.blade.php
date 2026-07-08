@extends('layouts.app')

@section('title', __('pages.live_quiz_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.live_quiz_title') }}</h1>
        @if(Auth::user()->isInstructorOrAdmin())
            <a href="{{ route('live-quiz.create') }}" class="btn btn-primary">{{ __('pages.live_quiz_create') }}</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(Auth::user()->isInstructorOrAdmin())
        @forelse($quizzes as $quiz)
            <div class="app-card card mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1">{{ $quiz->title }}</h5>
                        <small class="text-muted-theme">
                            {{ __('pages.live_quiz_code') }}: <strong>{{ $quiz->join_code }}</strong>
                            · {{ $quiz->mode === 'team' ? __('pages.live_quiz_mode_team') : __('pages.live_quiz_mode_individual') }}
                        </small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('live-quiz.builder', $quiz) }}" class="btn btn-outline-theme btn-sm">{{ __('pages.live_quiz_builder') }}</a>
                        <form method="POST" action="{{ route('live-quiz.host.start', $quiz) }}">@csrf
                            <button class="btn btn-primary btn-sm">{{ __('pages.live_quiz_start_session') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted-theme">{{ __('pages.live_quiz_none') }}</p>
        @endforelse
        {{ $quizzes->links() }}
    @else
        <div class="app-tile">
            <p class="mb-3">{{ __('pages.live_quiz_student_intro') }}</p>
            <a href="{{ route('live-quiz.play.join') }}" class="btn btn-primary">{{ __('pages.live_quiz_join') }}</a>
        </div>
    @endif
</div>
@endsection
