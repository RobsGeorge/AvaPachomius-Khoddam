@extends('layouts.app')

@section('title', __('pages.live_quiz_host_lobby'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <h1 class="page-title">{{ __('pages.live_quiz_host_lobby') }}</h1>
    <p class="lead">{{ $session->quiz->title }}</p>
    <div class="app-card card mb-4">
        <div class="card-body text-center p-4">
            <div class="display-4 fw-bold mb-2" id="join-code">{{ $session->join_code }}</div>
            <p class="text-muted-theme mb-0">{{ __('pages.live_quiz_share_code') }}</p>
            <p class="mt-3 mb-0"><span id="participant-count">{{ $session->participants->count() }}</span> {{ __('pages.live_quiz_participants') }}</p>
        </div>
    </div>

    @if($session->isTeamMode())
        <p>{{ __('pages.live_quiz_team_mode_info', ['count' => $session->team_count]) }}</p>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('live-quiz.host.present', $session) }}" class="btn btn-outline-theme" target="_blank">{{ __('pages.live_quiz_open_projector') }}</a>
        <a href="{{ route('live-quiz.host.control', $session) }}" class="btn btn-primary">{{ __('pages.live_quiz_go_control') }}</a>
    </div>

    <div class="app-card card">
        <div class="card-body">
            <h5>{{ __('pages.live_quiz_launch_question') }}</h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach($session->quiz->questions as $question)
                    <form method="POST" action="{{ route('live-quiz.host.launch', $session) }}">@csrf
                        <input type="hidden" name="order_index" value="{{ $question->order_index }}">
                        <button class="btn btn-success">#{{ $question->order_index }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</div>

<x-echo-reverb :channel="'live-quiz.'.$session->session_id" />
@endsection

@push('scripts')
<script>
window.addEventListener('live-session-updated', (e) => {
    const p = e.detail;
    if (p.participant_count !== undefined) {
        document.getElementById('participant-count').textContent = p.participant_count;
    }
});
</script>
@endpush
