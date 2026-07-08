@extends('layouts.app')

@section('title', __('pages.live_quiz_host_control'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title mb-0">{{ __('pages.live_quiz_host_control') }}</h1>
        <a href="{{ route('live-quiz.host.present', $session) }}" class="btn btn-outline-theme" target="_blank">{{ __('pages.live_quiz_projector') }}</a>
    </div>

    <div class="alert alert-info">{{ __('pages.live_quiz_status') }}: <strong id="session-status">{{ $session->status }}</strong></div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="app-card card h-100"><div class="card-body">
                <h5>{{ __('pages.live_quiz_launch_question') }}</h5>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($session->quiz->questions as $question)
                        <form method="POST" action="{{ route('live-quiz.host.launch', $session) }}">@csrf
                            <input type="hidden" name="order_index" value="{{ $question->order_index }}">
                            <button class="btn btn-sm btn-success">#{{ $question->order_index }}</button>
                        </form>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('live-quiz.host.results', $session) }}" class="d-inline">@csrf<button class="btn btn-warning">{{ __('pages.live_quiz_show_results') }}</button></form>
                <form method="POST" action="{{ route('live-quiz.host.end', $session) }}" class="d-inline ms-2" onsubmit="return confirm('{{ __('pages.confirm_end_session') }}')">@csrf<button class="btn btn-danger">{{ __('pages.end_session') }}</button></form>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="app-card card h-100"><div class="card-body">
                <h5>{{ __('pages.leaderboard') }}</h5>
                <ol id="leaderboard" class="mb-0">
                    @foreach($session->participants->sortByDesc('score') as $p)
                        <li>{{ $p->display_name }} — {{ $p->score }} @if($p->team_number)<span class="badge bg-secondary">T{{ $p->team_number }}</span>@endif</li>
                    @endforeach
                </ol>
            </div></div>
        </div>
    </div>
</div>
<x-echo-reverb :channel="'live-quiz.'.$session->session_id" />
@endsection

@push('scripts')
<script>
window.addEventListener('live-session-updated', (e) => {
    const p = e.detail;
    if (p.status) document.getElementById('session-status').textContent = p.status;
    if (p.leaderboard) {
        const ol = document.getElementById('leaderboard');
        ol.innerHTML = '';
        p.leaderboard.forEach(row => {
            const li = document.createElement('li');
            li.textContent = row.display_name ? `${row.display_name} — ${row.score}` : `Team ${row.team_number} — ${row.score}`;
            ol.appendChild(li);
        });
    }
});
</script>
@endpush
