@extends('layouts.app')

@section('title', __('pages.live_quiz_lobby'))

@section('content')
<div class="container py-4 animate-in text-center" style="max-width:640px;">
    <h1 class="page-title">{{ __('pages.live_quiz_lobby') }}</h1>
    <p class="lead">{{ $session->quiz->title }}</p>
    <p>{{ __('pages.live_quiz_waiting_host') }}</p>
    @if($session->isTeamMode() && $participant->team_number)
        <p class="badge bg-primary fs-6">{{ __('pages.live_quiz_your_team', ['team' => $participant->team_number]) }}</p>
    @endif
    <a href="{{ route('live-quiz.play.session', $session) }}" class="btn btn-primary mt-3" id="enter-btn">{{ __('pages.live_quiz_enter') }}</a>
</div>
<x-echo-reverb :channel="'live-quiz.'.$session->session_id" />
@endsection

@push('scripts')
<script>
window.addEventListener('live-session-updated', (e) => {
    if (e.detail.status === 'question') {
        window.location.href = @json(route('live-quiz.play.session', $session));
    }
});
</script>
@endpush
