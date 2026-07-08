<!DOCTYPE html>
@php $htmlDir = locale_dir(); $htmlLang = str_replace('_', '-', app()->getLocale()); @endphp
<html lang="{{ $htmlLang }}" dir="{{ $htmlDir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pages.live_feedback_projector') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#111827;color:#fff;min-height:100vh}.wrap{max-width:1100px;margin:0 auto;padding:2rem}.bar{height:1.5rem;background:#374151;border-radius:.5rem;overflow:hidden}.fill{height:100%;background:#6366f1}</style>
</head>
<body>
<div class="wrap">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="display-6 fw-bold">{{ $session->course->title }} — {{ $session->module->title }}</h1>
            <p class="lead mb-0">{{ __('pages.live_feedback_anonymous_aggregate') }}</p>
        </div>
        @if($session->isLive())
            <form method="POST" action="{{ route('live-feedback.close', $session) }}">@csrf<button class="btn btn-danger">{{ __('pages.close_session') }}</button></form>
        @endif
    </div>
    <p>{{ __('pages.responses_submitted') }}: <strong id="submitted-count">{{ $aggregates['submitted_count'] ?? 0 }}</strong></p>
    <div class="row g-4" id="aggregate-panels">
        @foreach(['lecture','speaker','workshop','timing','content'] as $key)
            <div class="col-md-6">
                <div class="border border-secondary rounded p-3 h-100">
                    <h4>{{ __('pages.rate_'.$key) }}</h4>
                    <p class="mb-2">{{ __('pages.average') }}: <span id="avg-{{ $key }}">{{ $aggregates[$key]['average'] ?? '—' }}</span></p>
                    <div id="bars-{{ $key }}">
                        @for($r = 5; $r >= 1; $r--)
                            @php $count = collect($aggregates[$key]['distribution'] ?? [])->firstWhere('rating', $r)['count'] ?? 0; @endphp
                            <div class="d-flex align-items-center gap-2 mb-1"><span style="width:1.5rem">{{ $r }}</span><div class="bar flex-grow-1"><div class="fill" style="width:{{ min(100, $count * 10) }}%"></div></div><span>{{ $count }}</span></div>
                        @endfor
                    </div>
                </div>
            </div>
        @endforeach
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
window.Echo.channel('live-feedback.{{ $session->session_id }}').listen('.session.updated', (p) => {
    document.getElementById('submitted-count').textContent = p.submitted_count || 0;
    const aggs = p.aggregates || {};
    Object.keys(aggs).forEach(key => {
        const avgEl = document.getElementById('avg-' + key);
        if (avgEl) avgEl.textContent = aggs[key].average ?? '—';
    });
});
</script>
</body>
</html>
