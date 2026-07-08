@extends('layouts.app')

@section('title', __('pages.live_quiz_play'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <h1 class="page-title text-center">{{ $session->quiz->title }}</h1>
    <div id="waiting" class="text-center py-5">
        <p class="lead">{{ __('pages.live_quiz_waiting_question') }}</p>
    </div>
    <div id="question-wrap" class="d-none">
        <div class="text-center mb-4">
            <div class="display-4 fw-bold" id="timer">--</div>
        </div>
        <div class="app-card card"><div class="card-body p-4 text-center">
            <h3 id="prompt-text" class="mb-3"></h3>
            <img id="prompt-image" class="img-fluid rounded mb-3 d-none" alt="">
            <form id="answer-form" method="POST">@csrf
                <div id="options" class="d-grid gap-2"></div>
            </form>
        </div></div>
    </div>
    <div id="results-wrap" class="d-none text-center py-5">
        <h3>{{ __('pages.live_quiz_session_complete') }}</h3>
        <p>{{ __('pages.live_quiz_your_score') }}: <strong id="my-score">{{ $participant->score }}</strong></p>
    </div>
</div>
<x-echo-reverb :channel="'live-quiz.'.$session->session_id" />
@endsection

@push('scripts')
<script>
const participantId = {{ $participant->participant_id }};
let timerInterval = null;
function renderQuestion(p) {
    document.getElementById('waiting').classList.add('d-none');
    document.getElementById('results-wrap').classList.add('d-none');
    if (p.status === 'ended') {
        document.getElementById('question-wrap').classList.add('d-none');
        document.getElementById('results-wrap').classList.remove('d-none');
        const me = (p.leaderboard || []).find(r => r.participant_id === participantId);
        if (me) document.getElementById('my-score').textContent = me.score;
        return;
    }
    if (p.status !== 'question' || !p.current_question) {
        document.getElementById('question-wrap').classList.add('d-none');
        document.getElementById('waiting').classList.remove('d-none');
        return;
    }
    document.getElementById('question-wrap').classList.remove('d-none');
    const q = p.current_question;
    document.getElementById('prompt-text').textContent = q.prompt_text || '';
    const img = document.getElementById('prompt-image');
    if (q.prompt_image_path) { img.src = '/storage/' + q.prompt_image_path; img.classList.remove('d-none'); } else img.classList.add('d-none');
    const opts = document.getElementById('options');
    opts.innerHTML = '';
    const form = document.getElementById('answer-form');
    form.action = `/live-quiz/sessions/{{ $session->session_id }}/questions/${q.question_id}/answer`;
    (q.options || []).forEach(opt => {
        const btn = document.createElement('button');
        btn.type = 'submit';
        btn.name = 'option_id';
        btn.value = opt.option_id;
        btn.className = 'btn btn-outline-primary btn-lg';
        btn.textContent = opt.label_text || ('Option ' + opt.order_index);
        opts.appendChild(btn);
    });
    clearInterval(timerInterval);
    const end = new Date(p.question_started_at).getTime() + q.time_limit_seconds * 1000;
    timerInterval = setInterval(() => {
        const remain = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        document.getElementById('timer').textContent = remain;
        if (remain <= 0) clearInterval(timerInterval);
    }, 250);
}
window.addEventListener('live-session-updated', (e) => renderQuestion(e.detail));
</script>
@endpush
