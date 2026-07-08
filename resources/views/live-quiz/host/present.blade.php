<!DOCTYPE html>
@php $htmlDir = locale_dir(); $htmlLang = str_replace('_', '-', app()->getLocale()); @endphp
<html lang="{{ $htmlLang }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $session->quiz->title }} — {{ __('pages.live_quiz_projector') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/khoddam-theme.css') }}">
    <style>
        body { background:#0f172a; color:#fff; min-height:100vh; }
        .projector-wrap { max-width:1200px; margin:0 auto; padding:2rem; }
        .join-code { font-size:4rem; letter-spacing:.3rem; font-weight:800; }
        .option-bar { height:2rem; background:#334155; border-radius:.5rem; overflow:hidden; margin-bottom:.75rem; }
        .option-fill { height:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6); transition:width .4s ease; }
    </style>
</head>
<body>
<div class="projector-wrap">
    <div class="text-center mb-4">
        <h1 class="display-5 fw-bold">{{ $session->quiz->title }}</h1>
        <div class="join-code" id="join-code">{{ $session->join_code }}</div>
        <p class="lead" id="status-line">{{ strtoupper($session->status) }}</p>
    </div>

    <div id="question-panel" class="d-none">
        <div class="text-center mb-4">
            <h2 id="prompt-text" class="display-6"></h2>
            <img id="prompt-image" class="img-fluid rounded mb-3 d-none" alt="">
            <div class="display-1 fw-bold" id="timer">--</div>
        </div>
        <div id="options-panel"></div>
    </div>

    <div id="leaderboard-panel" class="row justify-content-center d-none">
        <div class="col-md-8">
            <h3 class="text-center mb-4">{{ __('pages.leaderboard') }}</h3>
            <ol id="leaderboard-list" class="fs-4"></ol>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: @json(config('broadcasting.connections.reverb.key')),
    wsHost: @json(config('broadcasting.connections.reverb.options.host')),
    wsPort: {{ (int) config('broadcasting.connections.reverb.options.port') }},
    wssPort: {{ (int) config('broadcasting.connections.reverb.options.port') }},
    forceTLS: @json(config('broadcasting.connections.reverb.options.scheme') === 'https'),
    enabledTransports: ['ws', 'wss'],
});
let timerInterval = null;
function renderPayload(p) {
    document.getElementById('status-line').textContent = (p.status || '').toUpperCase();
    const qPanel = document.getElementById('question-panel');
    const lbPanel = document.getElementById('leaderboard-panel');
    if (p.status === 'question' && p.current_question) {
        qPanel.classList.remove('d-none');
        lbPanel.classList.add('d-none');
        const q = p.current_question;
        document.getElementById('prompt-text').textContent = q.prompt_text || '';
        const img = document.getElementById('prompt-image');
        if (q.prompt_image_path) { img.src = '/storage/' + q.prompt_image_path; img.classList.remove('d-none'); } else { img.classList.add('d-none'); }
        const opts = document.getElementById('options-panel');
        opts.innerHTML = '';
        const agg = p.aggregates || {};
        (q.options || []).forEach(opt => {
            const count = (agg.options || []).find(o => o.option_id === opt.option_id)?.count || 0;
            const total = agg.participant_count || 1;
            const pct = Math.round((count / total) * 100);
            opts.innerHTML += `<div class="mb-3"><div class="d-flex justify-content-between"><span>${opt.label_text || ''}</span><span>${count}</span></div><div class="option-bar"><div class="option-fill" style="width:${pct}%"></div></div></div>`;
        });
        startTimer(q.time_limit_seconds, p.question_started_at);
    } else if (p.status === 'results' || p.status === 'ended') {
        qPanel.classList.add('d-none');
        lbPanel.classList.remove('d-none');
        const list = document.getElementById('leaderboard-list');
        list.innerHTML = '';
        (p.leaderboard || []).forEach(row => {
            const li = document.createElement('li');
            li.textContent = row.display_name ? `${row.display_name} — ${row.score}` : `Team ${row.team_number} — ${row.score}`;
            list.appendChild(li);
        });
        clearInterval(timerInterval);
    } else {
        qPanel.classList.add('d-none');
        lbPanel.classList.add('d-none');
    }
}
function startTimer(seconds, startedAt) {
    clearInterval(timerInterval);
    const end = new Date(startedAt).getTime() + seconds * 1000;
    timerInterval = setInterval(() => {
        const remain = Math.max(0, Math.ceil((end - Date.now()) / 1000));
        document.getElementById('timer').textContent = remain;
        if (remain <= 0) clearInterval(timerInterval);
    }, 250);
}
window.Echo.channel('live-quiz.{{ $session->session_id }}').listen('.session.updated', renderPayload);
</script>
</body>
</html>
